<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackagePlanAudit;
use App\Models\PackagePlanVersion;
use App\Models\PackagePurchase;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\PackageAutoRoiControl;
use App\Services\PackageIncomeDefinitionResolver;
use App\Services\PackageIncomePolicy;
use App\Services\PackagePlanAdministration;
use App\Support\PackageValidityDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PackageController extends Controller
{
    public function index(Request $request, PackageIncomePolicy $policy, PackageAutoRoiControl $autoRoi): View
    {
        $packages = Package::query()
            ->with(['activePlanVersion'])
            ->withCount([
                'purchases as approved_purchases_count' => fn ($query) => $query->where('status', PackagePurchase::APPROVED),
                'purchases as total_purchases_count',
            ])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('q')).'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)->orWhere('code', 'like', $term);
                });
            })
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('is_active', $request->query('status') === 'active');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $incomeRules = [];
        $adminRoiById = [];
        $autoRoiById = [];
        foreach ($packages as $package) {
            $incomeRules[$package->code] = $policy->definition($package->code);
            // Admin list shows the managed plan ROI (what Admin saved), not a stale candidate/legacy fallback.
            $adminRoiById[$package->id] = $this->adminManagedRoi($package, $incomeRules[$package->code]);
            $autoRoiById[$package->id] = $autoRoi->effectiveStatus($package);
        }

        return view('admin.packages.index', [
            'packages' => $packages,
            'incomeRules' => $incomeRules,
            'adminRoiById' => $adminRoiById,
            'autoRoiById' => $autoRoiById,
        ]);
    }

    public function create(): View
    {
        return view('admin.packages.form', [
            'package' => new Package([
                'is_active' => true,
                'purchase_available' => true,
                'sort_order' => 0,
                'roi_eligible' => false,
                'auto_roi_enabled' => false,
                'package_type' => Package::TYPE_TOUR_ROI,
                'denomination' => 'USDT',
            ]),
            'plan' => new PackagePlanVersion([
                'has_daily_roi' => true,
                'wait_days' => (int) config('deltasupport.package_income.wait_days', 10),
                'roi_start_rule' => 'approval_plus_wait_days',
                'roi_end_rule' => 'first_accrual_plus_duration_minus_one',
                'food_coupon_currency' => 'INR',
            ]),
            'displayRoi' => ['daily' => null, 'days' => null],
            'autoRoiStatus' => null,
            'mode' => 'create',
        ]);
    }

    public function store(Request $request, PackagePlanAdministration $plans): RedirectResponse
    {
        $admin = $this->admin($request);
        $data = $this->validatedSimpleCatalog($request);
        $plan = $this->validatedSimplePlan($request, (string) $data['package_type'], null);

        $ledgerBefore = WalletLedgerEntry::query()->count();
        $package = $plans->createPackage($data, $plan, $admin, $request->ip());
        try {
            $this->storeImage($request, $package);
        } catch (ValidationException $exception) {
            $package->delete();
            throw $exception;
        }

        abort_unless(WalletLedgerEntry::query()->count() === $ledgerBefore, 500);

        return redirect()
            ->route('admin.packages.edit', $package)
            ->with('success', 'Package created. Auto ROI stays OFF until you turn it ON. No wallets were credited.');
    }

    public function show(Package $package, PackageIncomePolicy $policy, PackagePlanAdministration $plans, PackageAutoRoiControl $autoRoi): View
    {
        $package->load(['activePlanVersion', 'planVersions' => fn ($q) => $q->orderByDesc('version_number')]);

        return view('admin.packages.show', [
            'package' => $package,
            'plan' => $package->activePlanVersion,
            'incomeRule' => $policy->definition((string) $package->code),
            'status' => $plans->statusIndicators($package),
            'autoRoiStatus' => $autoRoi->effectiveStatus($package),
            'versions' => $package->planVersions,
            'audits' => PackagePlanAudit::query()->where('package_id', $package->id)->latest('id')->limit(50)->get(),
        ]);
    }

    public function edit(Package $package, PackageIncomePolicy $policy, PackagePlanAdministration $plans, PackageAutoRoiControl $autoRoi): View
    {
        // Fresh model so Auto ROI status matches packages.auto_roi_enabled in the database.
        $package = $package->fresh(['activePlanVersion']) ?? $package->load(['activePlanVersion']);
        $editingPlan = $plans->editingPlanVersion($package);
        $definition = $policy->definition((string) $package->code);
        $plan = $editingPlan ?? new PackagePlanVersion([
            'has_daily_roi' => (bool) $package->roi_eligible,
            'wait_days' => (int) config('deltasupport.package_income.wait_days', 10),
            'roi_start_rule' => 'approval_plus_wait_days',
            'roi_end_rule' => 'first_accrual_plus_duration_minus_one',
            'food_coupon_currency' => 'INR',
        ]);
        // Editable field must bind to the managed plan row Admin last saved — never inject candidate 0.1058.
        $managed = $this->adminManagedRoi($package, $definition);
        if ($plan->roi_daily === null && ($managed['daily'] ?? null) !== null) {
            $plan->roi_daily = $managed['daily'];
            $plan->roi_days = $managed['days'];
            $plan->has_daily_roi = true;
        }

        $activePublished = $package->activePlanVersion;
        $needsAccrualAuthorization = $package->reservesFutureIncome()
            && $activePublished
            && $activePublished->has_daily_roi
            && $activePublished->roi_daily !== null
            && $activePublished->roi_days !== null
            && (! $activePublished->accrual_authority
                || (string) ($package->income_source ?: Package::INCOME_SOURCE_LEGACY) !== Package::INCOME_SOURCE_DATABASE
                || $definition === null);

        return view('admin.packages.form', [
            'package' => $package,
            'plan' => $plan,
            'displayRoi' => [
                'daily' => $plan->roi_daily,
                'days' => $plan->roi_days,
            ],
            'autoRoiStatus' => $autoRoi->effectiveStatus($package),
            'needsAccrualAuthorization' => $needsAccrualAuthorization,
            'ambiguousHistoricalCount' => app(PackageIncomeDefinitionResolver::class)
                ->ambiguousApprovedPurchases($package)
                ->count(),
            'mode' => 'edit',
            'codeLocked' => $package->purchases()->exists(),
        ]);
    }

    public function update(Request $request, Package $package, PackagePlanAdministration $plans): RedirectResponse
    {
        $admin = $this->admin($request);
        $ledgerBefore = WalletLedgerEntry::query()->count();
        $catalog = $this->validatedSimpleCatalog($request, $package);
        $plan = $this->validatedSimplePlan($request, (string) ($catalog['package_type'] ?? $package->package_type), $package);

        // Income-source cutover only when explicitly posted (hidden from the simple form UI).
        if ($request->filled('income_source')) {
            $request->validate([
                'income_source' => ['required', Rule::in([Package::INCOME_SOURCE_LEGACY, Package::INCOME_SOURCE_DATABASE])],
                'confirm_income_source_change' => ['nullable', 'boolean'],
            ]);
            $plans->setIncomeSource(
                $package->fresh(),
                (string) $request->input('income_source'),
                $request->boolean('confirm_income_source_change'),
                $admin,
                $request->ip()
            );
        }

        // Publish plan for future purchases only. Never implied wallet credit.
        $plans->savePlanVersion($package->fresh(), $plan, $admin, true, $request->ip());
        $plans->updateCatalog($package->fresh(), $catalog, $admin, $request->ip());
        $this->storeImage($request, $package->fresh());

        abort_unless(WalletLedgerEntry::query()->count() === $ledgerBefore, 500);

        $fresh = $package->fresh(['activePlanVersion']);
        $savedDaily = $fresh->activePlanVersion?->has_daily_roi
            ? (string) $fresh->activePlanVersion->roi_daily
            : null;

        return redirect()
            ->route('admin.packages.edit', $fresh)
            ->with('success', $savedDaily !== null
                ? 'Package saved. Daily ROI is now '.$savedDaily.' USDT for future purchases. Existing approved snapshots were not changed. No wallets were credited.'
                : 'Package saved. Existing approved purchase snapshots were not changed. No wallets were credited.');
    }

    public function updateAutoRoi(Request $request, Package $package, PackageAutoRoiControl $autoRoi): RedirectResponse
    {
        $admin = $this->admin($request);
        $request->validate([
            'auto_roi_enabled' => ['required', 'boolean'],
            'confirm_auto_roi' => ['nullable', 'boolean'],
        ]);

        $autoRoi->setEnabled(
            $package,
            $request->boolean('auto_roi_enabled'),
            $admin,
            $request->boolean('confirm_auto_roi'),
            $request->ip()
        );

        return redirect()
            ->route('admin.packages.edit', $package->fresh())
            ->with('success', $request->boolean('auto_roi_enabled')
                ? 'Auto ROI turned ON. Eligible accruals wait for the next authorized scheduled run. No wallets were credited.'
                : 'Auto ROI turned OFF. New package ROI accruals are stopped. Historical accruals were not deleted.');
    }

    public function authorizeAccrual(Request $request, Package $package, PackagePlanAdministration $plans): RedirectResponse
    {
        $admin = $this->admin($request);
        $request->validate([
            'confirm_authorize_accrual' => ['nullable', 'boolean'],
        ]);

        $ledgerBefore = WalletLedgerEntry::query()->count();
        $version = $plans->authorizeAccrualPlan(
            $package,
            $admin,
            $request->boolean('confirm_authorize_accrual'),
            $request->ip()
        );
        abort_unless(WalletLedgerEntry::query()->count() === $ledgerBefore, 500);

        return redirect()
            ->route('admin.packages.edit', $package->fresh())
            ->with('success', 'Plan v'.$version->version_number.' authorized for future purchase ROI. Existing approved purchases were not rewritten. No wallets were credited.');
    }

    public function updateStatus(Request $request, Package $package, PackagePlanAdministration $plans): RedirectResponse
    {
        $admin = $this->admin($request);
        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);
        $ledgerBefore = WalletLedgerEntry::query()->count();
        $plans->setActive($package, $request->boolean('is_active'), $admin, $request->ip());
        abort_unless(WalletLedgerEntry::query()->count() === $ledgerBefore, 500);

        return back()->with('success', $request->boolean('is_active')
            ? 'Package activated for catalog/purchase. Income posting was not authorized by this action.'
            : 'Package deactivated. Existing purchases were not changed.');
    }

    public function publishPlan(Request $request, Package $package, PackagePlanAdministration $plans): RedirectResponse
    {
        $admin = $this->admin($request);
        $plan = $this->validatedPlan($request, (string) $package->package_type);
        $ledgerBefore = WalletLedgerEntry::query()->count();
        $plans->savePlanVersion($package, $plan, $admin, true, $request->ip());
        abort_unless(WalletLedgerEntry::query()->count() === $ledgerBefore, 500);

        return redirect()
            ->route('admin.packages.show', $package)
            ->with('success', 'Plan version saved for future purchases. Historical ROI was not recalculated.');
    }

    public function image(Package $package): BinaryFileResponse
    {
        return $this->imageResponse($package, false);
    }

    public function publicImage(Package $package): BinaryFileResponse
    {
        return $this->imageResponse($package, true);
    }

    private function admin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_admin === true, 403);

        return $user;
    }

    /**
     * Simple Admin catalog fields. Does not accept income_source or advanced financial flags.
     *
     * @return array<string, mixed>
     */
    private function validatedSimpleCatalog(Request $request, ?Package $package = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('packages', 'code')->ignore($package)],
            'name' => ['required', 'string', 'max:120'],
            'package_type' => ['required', Rule::in([Package::TYPE_TOUR, Package::TYPE_TOUR_ROI, Package::TYPE_PREMIUM])],
            'price_usd' => ['required', 'regex:/^\d{1,12}(\.\d{1,4})?$/'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active');
        $data['purchase_available'] = $package?->purchase_available ?? true;
        $type = (string) $data['package_type'];
        $wantsRoi = $type !== Package::TYPE_TOUR;
        $hasRoiInput = $wantsRoi && filled($request->input('roi_daily')) && filled($request->input('roi_days'));
        $data['roi_eligible'] = $hasRoiInput;
        // Preserve existing level income eligibility; never flip from the simple form.
        $data['level_commission_eligible'] = $package?->level_commission_eligible ?? $wantsRoi;
        unset($data['image']);

        return $data;
    }

    /**
     * Simple plan fields for the eight business inputs. Preserves legacy commission pool when present.
     *
     * @return array<string, mixed>
     */
    private function validatedSimplePlan(Request $request, string $packageType, ?Package $package): array
    {
        $wantsRoi = in_array($packageType, [Package::TYPE_TOUR_ROI, Package::TYPE_PREMIUM, Package::TYPE_ROI], true);
        $data = $request->validate([
            'roi_daily' => ['nullable', 'regex:/^\d{1,12}(\.\d{1,4})?$/'],
            'roi_days' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'tour_nights' => ['nullable', 'integer', 'min:0', 'max:365'],
            'tour_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'tour_adults' => ['nullable', 'integer', 'min:0', 'max:50'],
            'tour_children' => ['nullable', 'integer', 'min:0', 'max:50'],
            'food_coupon_amount' => ['nullable', 'regex:/^\d{1,12}(\.\d{1,4})?$/'],
            'food_coupon_currency' => ['nullable', 'string', 'max:16'],
            'validity_unlimited' => ['nullable', 'boolean'],
            'validity_duration' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'validity_unit' => ['nullable', Rule::in(array_keys(PackageValidityDisplay::UNITS))],
        ]);

        $data['validity_unlimited'] = $request->boolean('validity_unlimited');
        if ($data['validity_unlimited']) {
            $data['validity_duration'] = null;
            $data['validity_unit'] = null;
        } else {
            $rawDuration = $request->input('validity_duration');
            $rawUnit = $request->input('validity_unit');
            $hasDuration = $rawDuration !== null && $rawDuration !== '';
            $hasUnit = is_string($rawUnit) && $rawUnit !== '';
            if ($hasDuration xor $hasUnit) {
                throw ValidationException::withMessages([
                    'validity_duration' => 'Enter both validity duration and unit, or leave both blank, or choose Unlimited.',
                ]);
            }
            if (! $hasDuration) {
                $data['validity_duration'] = null;
                $data['validity_unit'] = null;
            }
        }

        if ($packageType === Package::TYPE_TOUR) {
            if (filled($request->input('roi_daily')) || filled($request->input('roi_days'))) {
                throw ValidationException::withMessages([
                    'roi_daily' => 'Tour Only packages cannot have Daily ROI.',
                ]);
            }
            $data['has_daily_roi'] = false;
            $data['roi_daily'] = null;
            $data['roi_days'] = null;
        } else {
            $rawDaily = trim((string) $request->input('roi_daily', ''));
            $rawDays = $request->input('roi_days');
            $hasRoi = $rawDaily !== '' && $rawDays !== null && $rawDays !== '';
            $data['has_daily_roi'] = $hasRoi;
            if ($hasRoi) {
                // Persist the exact validated decimal Admin typed (normalized to 4 dp via BCMath later).
                $data['roi_daily'] = $rawDaily;
                $data['roi_days'] = (int) $rawDays;
            } else {
                $data['roi_daily'] = null;
                $data['roi_days'] = null;
                if ($rawDaily !== '' || ($rawDays !== null && $rawDays !== '')) {
                    throw ValidationException::withMessages([
                        'roi_daily' => 'Enter both Daily ROI (USDT) and ROI duration (days), or leave both blank.',
                    ]);
                }
            }
        }

        $data['wait_days'] = (int) config('deltasupport.package_income.wait_days', 10);
        $data['roi_start_rule'] = 'approval_plus_wait_days';
        $data['roi_end_rule'] = 'first_accrual_plus_duration_minus_one';
        $data['level_commission_eligible'] = (bool) ($package?->level_commission_eligible ?? $wantsRoi);
        // Preserve existing commission pool from active plan; do not invent Associate rates.
        $existing = $package?->activePlanVersion;
        $data['commission_pool'] = $existing?->commission_pool;
        $data['commission_days'] = $existing?->commission_days;
        // Request authority; savePlanVersion still ANDs with income_source === database (never silent cutover).
        $data['accrual_authority'] = true;
        $data['change_note'] = 'simple_admin_package_save';

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPlan(Request $request, string $packageType): array
    {
        return $this->validatedSimplePlan($request, $packageType, null);
    }

    /**
     * ROI amounts shown/edited in Admin catalog screens.
     * Prefer the active published plan version Admin manages; fall back to resolver definition.
     * Never invents candidate rates such as 0.1058.
     *
     * @param  array<string, mixed>|null  $definition
     * @return array{daily: ?string, days: ?int}
     */
    private function adminManagedRoi(Package $package, ?array $definition): array
    {
        $plan = $package->relationLoaded('activePlanVersion')
            ? $package->activePlanVersion
            : $package->activePlanVersion()->first();

        if ($plan && $plan->has_daily_roi && $plan->roi_daily !== null && $plan->roi_days !== null) {
            return [
                'daily' => bcadd((string) $plan->roi_daily, '0', 4),
                'days' => (int) $plan->roi_days,
            ];
        }

        if ($definition !== null && isset($definition['roi_daily'], $definition['roi_days'])) {
            return [
                'daily' => bcadd((string) $definition['roi_daily'], '0', 4),
                'days' => (int) $definition['roi_days'],
            ];
        }

        return ['daily' => null, 'days' => null];
    }

    private function storeImage(Request $request, Package $package): void
    {
        if ($request->boolean('remove_image') && ! $request->hasFile('image')) {
            $previous = $package->image_path;
            $package->forceFill(['image_path' => null])->save();
            $this->deleteStoredPackageImage($previous);

            return;
        }

        if (! $request->hasFile('image')) {
            return;
        }

        $file = $request->file('image');
        $path = $file->store('packages', 'local');
        $storedMime = is_string($path) ? mime_content_type(Storage::disk('local')->path($path)) : false;
        if (! is_string($path) || ! in_array($storedMime, ['image/jpeg', 'image/png', 'image/webp'], true) || ! $this->isPackageImagePath($path)) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }
            throw ValidationException::withMessages([
                'image' => 'Upload a JPG, PNG, or WebP image.',
            ]);
        }

        $previous = $package->image_path;
        $package->forceFill(['image_path' => $path])->save();
        $this->deleteStoredPackageImage($previous, $path);
    }

    private function deleteStoredPackageImage(?string $path, ?string $except = null): void
    {
        if ($path === null || $path === '' || $path === $except || ! $this->isPackageImagePath($path)) {
            return;
        }

        Storage::disk('local')->delete($path);
    }

    private function isPackageImagePath(string $path): bool
    {
        return (str_starts_with($path, 'packages/') || str_starts_with($path, 'package-images/'))
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\')
            && ! str_starts_with($path, '/');
    }

    private function imageResponse(Package $package, bool $activeOnly): BinaryFileResponse
    {
        abort_unless(
            (! $activeOnly || $package->is_active)
                && is_string($package->image_path)
                && $this->isPackageImagePath($package->image_path)
                && Storage::disk('local')->exists($package->image_path),
            404
        );

        return response()->file(Storage::disk('local')->path($package->image_path));
    }
}
