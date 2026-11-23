<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackagePlanAudit;
use App\Models\PackagePlanVersion;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\AssociateCommissionStructure;
use App\Services\PackageIncomePolicy;
use App\Services\PackagePlanAdministration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PackageController extends Controller
{
    public function index(Request $request, PackageIncomePolicy $policy, AssociateCommissionStructure $associate, PackagePlanAdministration $plans): View
    {
        $packages = Package::query()
            ->with(['activePlanVersion'])
            ->withCount('purchases')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('q')).'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)->orWhere('code', 'like', $term);
                });
            })
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('is_active', $request->query('status') === 'active');
            })
            ->when($request->filled('type'), function ($query) use ($request): void {
                $query->where('package_type', (string) $request->query('type'));
            })
            ->when($request->filled('roi'), function ($query) use ($request): void {
                $query->where('roi_eligible', $request->query('roi') === 'yes');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $associatePreview = $associate->preview();
        $incomeRules = [];
        $statusById = [];
        $associateByCode = [];
        foreach ($associatePreview['tiers'] as $tier) {
            if (! empty($tier['mapped_package_code'])) {
                $associateByCode[$tier['mapped_package_code']] = $tier;
            }
        }
        foreach ($packages as $package) {
            $incomeRules[$package->code] = $policy->definition($package->code);
            $statusById[$package->id] = $plans->statusIndicators($package);
        }

        return view('admin.packages.index', [
            'packages' => $packages,
            'incomeRules' => $incomeRules,
            'statusById' => $statusById,
            'associateByCode' => $associateByCode,
            'associatePreview' => $associatePreview,
        ]);
    }

    public function create(AssociateCommissionStructure $associate): View
    {
        return view('admin.packages.form', [
            'package' => new Package([
                'is_active' => true,
                'purchase_available' => true,
                'sort_order' => 0,
                'roi_eligible' => false,
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
            'incomeRule' => null,
            'status' => null,
            'associateTier' => null,
            'associatePreview' => $associate->preview(),
            'versions' => collect(),
            'audits' => collect(),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request, PackagePlanAdministration $plans): RedirectResponse
    {
        $admin = $this->admin($request);
        $data = $this->validatedCatalog($request);
        $plan = $this->validatedPlan($request, (string) $data['package_type']);

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
            ->route('admin.packages.index')
            ->with('success', 'Package created. Catalog Active does not authorize wallet income posting.');
    }

    public function show(Package $package, PackageIncomePolicy $policy, AssociateCommissionStructure $associate, PackagePlanAdministration $plans): View
    {
        $package->load(['activePlanVersion', 'planVersions' => fn ($q) => $q->orderByDesc('version_number')]);
        $tierCode = $associate->resolveTierForPackageCode((string) $package->code);
        $preview = $associate->preview();
        $tier = $tierCode === null ? null : collect($preview['tiers'])->firstWhere('tier_code', $tierCode);

        return view('admin.packages.show', [
            'package' => $package,
            'plan' => $package->activePlanVersion,
            'incomeRule' => $policy->definition((string) $package->code),
            'status' => $plans->statusIndicators($package),
            'associateTier' => $tier,
            'associatePreview' => $preview,
            'versions' => $package->planVersions,
            'audits' => PackagePlanAudit::query()->where('package_id', $package->id)->latest('id')->limit(50)->get(),
        ]);
    }

    public function edit(Package $package, PackageIncomePolicy $policy, AssociateCommissionStructure $associate, PackagePlanAdministration $plans): View
    {
        $package->load(['activePlanVersion', 'planVersions' => fn ($q) => $q->orderByDesc('version_number')]);
        $tierCode = $associate->resolveTierForPackageCode((string) $package->code);
        $preview = $associate->preview();
        $tier = $tierCode === null ? null : collect($preview['tiers'])->firstWhere('tier_code', $tierCode);
        $plan = $package->activePlanVersion ?? new PackagePlanVersion([
            'has_daily_roi' => (bool) $package->roi_eligible,
            'wait_days' => (int) config('deltasupport.package_income.wait_days', 10),
        ]);

        return view('admin.packages.form', [
            'package' => $package,
            'plan' => $plan,
            'incomeRule' => $policy->definition((string) $package->code),
            'status' => $plans->statusIndicators($package),
            'associateTier' => $tier,
            'associatePreview' => $preview,
            'versions' => $package->planVersions,
            'audits' => PackagePlanAudit::query()->where('package_id', $package->id)->latest('id')->limit(30)->get(),
            'mode' => 'edit',
            'codeLocked' => $package->purchases()->exists(),
        ]);
    }

    public function update(Request $request, Package $package, PackagePlanAdministration $plans): RedirectResponse
    {
        $admin = $this->admin($request);
        $ledgerBefore = WalletLedgerEntry::query()->count();
        $catalog = $this->validatedCatalog($request, $package);
        $plans->updateCatalog($package, $catalog, $admin, $request->ip());
        $this->storeImage($request, $package->fresh());

        if ($request->boolean('save_plan')) {
            $plan = $this->validatedPlan($request, (string) ($catalog['package_type'] ?? $package->package_type));
            $plans->savePlanVersion($package->fresh(), $plan, $admin, $request->boolean('publish_plan'), $request->ip());
        }

        abort_unless(WalletLedgerEntry::query()->count() === $ledgerBefore, 500);

        return redirect()
            ->route('admin.packages.index')
            ->with('success', 'Package updated. Existing approved purchase snapshots were not changed. No wallets were credited.');
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
     * @return array<string, mixed>
     */
    private function validatedCatalog(Request $request, ?Package $package = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('packages', 'code')->ignore($package)],
            'name' => ['required', 'string', 'max:120'],
            'package_type' => ['nullable', Rule::in(array_keys(Package::TYPES))],
            'description' => ['nullable', 'string', 'max:500'],
            'full_description' => ['nullable', 'string', 'max:10000'],
            'price_usd' => ['required', 'regex:/^\d{1,12}(\.\d{1,4})?$/'],
            'tour_title' => ['nullable', 'string', 'max:160'],
            'tour_details' => ['nullable', 'string', 'max:5000'],
            'destinations' => ['nullable', 'string', 'max:2000'],
            'duration' => ['nullable', 'string', 'max:80'],
            'benefits' => ['nullable', 'string', 'max:5000'],
            'inclusions' => ['nullable', 'string', 'max:5000'],
            'exclusions' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
            'purchase_available' => ['nullable', 'boolean'],
            'roi_eligible' => ['nullable', 'boolean'],
            'level_commission_eligible' => ['nullable', 'boolean'],
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['package_type'] = $data['package_type'] ?? Package::TYPE_TOUR;
        $data['is_active'] = $request->boolean('is_active');
        $data['purchase_available'] = $request->exists('purchase_available')
            ? $request->boolean('purchase_available')
            : true;
        $type = (string) $data['package_type'];
        $reservesIncome = $type !== Package::TYPE_TOUR;
        // When package_type is submitted, map eligibility from type (legacy Admin behavior).
        // Explicit checkbox fields still win when present alongside advanced forms.
        if ($request->exists('package_type')) {
            $data['roi_eligible'] = $request->exists('roi_eligible')
                ? $request->boolean('roi_eligible')
                : $reservesIncome;
            $data['level_commission_eligible'] = $request->exists('level_commission_eligible')
                ? $request->boolean('level_commission_eligible')
                : $reservesIncome;
        } else {
            $data['roi_eligible'] = $request->boolean('roi_eligible');
            $data['level_commission_eligible'] = $request->boolean('level_commission_eligible');
        }
        unset($data['image']);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPlan(Request $request, string $packageType): array
    {
        $wantsRoi = in_array($packageType, [Package::TYPE_TOUR_ROI, Package::TYPE_PREMIUM, Package::TYPE_ROI], true);
        $data = $request->validate([
            'has_daily_roi' => ['nullable', 'boolean'],
            'roi_daily' => ['nullable', 'regex:/^\d{1,12}(\.\d{1,4})?$/'],
            'roi_days' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'wait_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'roi_start_rule' => ['nullable', 'string', 'max:64'],
            'roi_end_rule' => ['nullable', 'string', 'max:64'],
            'commission_pool' => ['nullable', 'regex:/^\d{1,12}(\.\d{1,4})?$/'],
            'commission_days' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'level_commission_eligible' => ['nullable', 'boolean'],
            'tour_eligible_after_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'tour_nights' => ['nullable', 'integer', 'min:0', 'max:365'],
            'tour_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'tour_adults' => ['nullable', 'integer', 'min:0', 'max:50'],
            'tour_children' => ['nullable', 'integer', 'min:0', 'max:50'],
            'food_coupon_amount' => ['nullable', 'regex:/^\d{1,12}(\.\d{1,4})?$/'],
            'food_coupon_currency' => ['nullable', 'string', 'max:16'],
            'tour_description' => ['nullable', 'string', 'max:5000'],
            'change_note' => ['nullable', 'string', 'max:2000'],
            'label' => ['nullable', 'string', 'max:120'],
            'accrual_authority' => ['nullable', 'boolean'],
        ]);

        if ($packageType === Package::TYPE_TOUR && $request->boolean('has_daily_roi')) {
            throw ValidationException::withMessages([
                'has_daily_roi' => 'Tour Only packages cannot have Daily ROI.',
            ]);
        }

        // Require an explicit checkbox; do not invent ROI terms for catalog-only creates.
        $hasRoi = $wantsRoi && $request->boolean('has_daily_roi');
        if ($packageType === Package::TYPE_TOUR) {
            $hasRoi = false;
        }
        $data['has_daily_roi'] = $hasRoi;
        $data['level_commission_eligible'] = $request->boolean('level_commission_eligible');
        // Never enable accrual authority for legacy_config packages from this form.
        $data['accrual_authority'] = $request->boolean('accrual_authority');

        return $data;
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
