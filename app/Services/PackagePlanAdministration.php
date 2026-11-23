<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\Package;
use App\Models\PackagePlanAudit;
use App\Models\PackagePlanVersion;
use App\Models\PackagePurchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Admin package catalog + versioned plan management.
 * Never credits wallets. Never enables associate posting or financial arming.
 */
class PackagePlanAdministration
{
    public function __construct(private readonly PackageIncomeDefinitionResolver $resolver)
    {
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @param  array<string, mixed>  $plan
     */
    public function createPackage(array $catalog, array $plan, User $admin, ?string $ip = null): Package
    {
        return DB::transaction(function () use ($catalog, $plan, $admin, $ip): Package {
            $code = strtoupper((string) $catalog['code']);
            if (Package::query()->where('code', $code)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['code' => 'Package code already exists.']);
            }

            $type = (string) ($catalog['package_type'] ?? Package::TYPE_TOUR);
            $wantsRoi = $this->typeWantsRoi($type);
            $this->assertRoiPlan($plan, $wantsRoi);

            $package = Package::query()->create([
                'code' => $code,
                'name' => $catalog['name'],
                'package_type' => $type,
                'description' => $catalog['description'] ?? null,
                'full_description' => $catalog['full_description'] ?? null,
                'price_usd' => bcadd((string) $catalog['price_usd'], '0', 4),
                'denomination' => 'USDT',
                'tour_title' => $catalog['tour_title'] ?? null,
                'tour_details' => $catalog['tour_details'] ?? ($plan['tour_description'] ?? null),
                'destinations' => $catalog['destinations'] ?? null,
                'duration' => $catalog['duration'] ?? null,
                'benefits' => $catalog['benefits'] ?? null,
                'inclusions' => $catalog['inclusions'] ?? null,
                'exclusions' => $catalog['exclusions'] ?? null,
                'roi_eligible' => (bool) ($catalog['roi_eligible'] ?? ($wantsRoi && (bool) ($plan['has_daily_roi'] ?? false))),
                'auto_roi_enabled' => false,
                'level_commission_eligible' => (bool) ($catalog['level_commission_eligible'] ?? false),
                'is_active' => (bool) ($catalog['is_active'] ?? true),
                'purchase_available' => (bool) ($catalog['purchase_available'] ?? true),
                'income_source' => $this->defaultIncomeSource($code),
                'sort_order' => (int) ($catalog['sort_order'] ?? 0),
            ]);

            $accrualAuthority = (string) $package->income_source === Package::INCOME_SOURCE_DATABASE
                && (bool) ($plan['has_daily_roi'] ?? false);
            $version = $this->insertVersion($package, $plan, $admin, PackagePlanVersion::STATUS_PUBLISHED, $accrualAuthority);
            $package->forceFill([
                'active_plan_version_id' => $version->id,
            ])->save();

            $this->audit($package, $version, $admin, 'package_created', null, $this->snapshotPackage($package), $ip);
            $this->audit($package, $version, $admin, 'plan_published', null, $version->toArray(), $ip);
            $this->activity($admin, 'package_created', $package->code);

            return $package->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $catalog
     */
    public function updateCatalog(Package $package, array $catalog, User $admin, ?string $ip = null): Package
    {
        return DB::transaction(function () use ($package, $catalog, $admin, $ip): Package {
            $locked = Package::query()->whereKey($package->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshotPackage($locked);

            if (array_key_exists('code', $catalog) && strtoupper((string) $catalog['code']) !== $locked->code) {
                if ($locked->purchases()->exists()) {
                    throw ValidationException::withMessages([
                        'code' => 'Package code cannot change after purchases exist.',
                    ]);
                }
                $newCode = strtoupper((string) $catalog['code']);
                if (Package::query()->where('code', $newCode)->where('id', '!=', $locked->id)->exists()) {
                    throw ValidationException::withMessages(['code' => 'Package code already exists.']);
                }
                $locked->code = $newCode;
            }

            foreach (['name', 'description', 'full_description', 'tour_title', 'tour_details', 'destinations', 'duration', 'benefits', 'inclusions', 'exclusions'] as $field) {
                if (array_key_exists($field, $catalog)) {
                    $locked->{$field} = $catalog[$field];
                }
            }
            if (array_key_exists('price_usd', $catalog)) {
                $locked->price_usd = bcadd((string) $catalog['price_usd'], '0', 4);
            }
            if (array_key_exists('package_type', $catalog) && $catalog['package_type'] !== null) {
                $locked->package_type = (string) $catalog['package_type'];
            }
            if (array_key_exists('sort_order', $catalog)) {
                $locked->sort_order = (int) $catalog['sort_order'];
            }
            if (array_key_exists('is_active', $catalog)) {
                $locked->is_active = (bool) $catalog['is_active'];
            }
            if (array_key_exists('purchase_available', $catalog)) {
                $locked->purchase_available = (bool) $catalog['purchase_available'];
            }
            if (array_key_exists('roi_eligible', $catalog)) {
                $locked->roi_eligible = (bool) $catalog['roi_eligible'];
            }
            if (array_key_exists('level_commission_eligible', $catalog)) {
                $locked->level_commission_eligible = (bool) $catalog['level_commission_eligible'];
            }

            if ($locked->is_active && $this->typeWantsRoi((string) $locked->package_type) && $locked->roi_eligible) {
                $this->assertActiveRoiReady($locked);
            }

            $locked->save();
            $this->audit($locked, null, $admin, 'catalog_updated', $before, $this->snapshotPackage($locked), $ip);
            $this->activity($admin, 'package_catalog_updated', $locked->code);

            return $locked->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    public function savePlanVersion(Package $package, array $plan, User $admin, bool $publish, ?string $ip = null): PackagePlanVersion
    {
        return DB::transaction(function () use ($package, $plan, $admin, $publish, $ip): PackagePlanVersion {
            $locked = Package::query()->whereKey($package->id)->lockForUpdate()->firstOrFail();
            $wantsRoi = $this->typeWantsRoi((string) $locked->package_type);
            $this->assertRoiPlan($plan, $wantsRoi);

            $hasApproved = PackagePurchase::query()
                ->where('package_id', $locked->id)
                ->where('status', PackagePurchase::APPROVED)
                ->exists();

            $status = $publish ? PackagePlanVersion::STATUS_PUBLISHED : PackagePlanVersion::STATUS_DRAFT;
            // New packages / unpublished: accrual_authority true only when publishing for database-source packages.
            // Legacy-config packages never get accrual_authority from Admin CRUD (activation blocker).
            $accrualAuthority = $publish
                && (string) $locked->income_source === 'database'
                && (bool) ($plan['accrual_authority'] ?? true);

            // Prefer the latest draft for Admin edits so saved Daily ROI reloads correctly.
            $draft = PackagePlanVersion::query()
                ->where('package_id', $locked->id)
                ->where('status', PackagePlanVersion::STATUS_DRAFT)
                ->orderByDesc('version_number')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($draft !== null && ! $publish) {
                $before = $draft->toArray();
                $this->fillVersion($draft, $plan, false);
                $draft->save();
                $this->audit($locked, $draft, $admin, 'plan_draft_updated', $before, $draft->fresh()->toArray(), $ip);

                return $draft->fresh();
            }

            if ($draft !== null && $publish) {
                // Promote the draft. Never mutates other published/historical versions.
                $before = $draft->toArray();
                $this->fillVersion($draft, $plan, $accrualAuthority);
                $draft->status = PackagePlanVersion::STATUS_PUBLISHED;
                $draft->published_at = now();
                $draft->published_by = $admin->id;
                $draft->accrual_authority = $accrualAuthority;
                $draft->save();
                $this->supersedeOthers($locked, $draft);
                $this->discardOtherDrafts($locked, $draft);
                $locked->forceFill(['active_plan_version_id' => $draft->id])->save();
                $this->syncCatalogFlagsFromPlan($locked, $draft);
                $this->audit(
                    $locked,
                    $draft,
                    $admin,
                    $hasApproved ? 'plan_published_new_version' : 'plan_published',
                    $before,
                    $draft->fresh()->toArray(),
                    $ip
                );

                return $draft->fresh();
            }

            $current = $locked->active_plan_version_id
                ? PackagePlanVersion::query()->whereKey($locked->active_plan_version_id)->lockForUpdate()->first()
                : null;

            if ($current && $current->status === PackagePlanVersion::STATUS_DRAFT) {
                $before = $current->toArray();
                $this->fillVersion($current, $plan, $accrualAuthority);
                if ($publish) {
                    $current->status = PackagePlanVersion::STATUS_PUBLISHED;
                    $current->published_at = now();
                    $current->published_by = $admin->id;
                    $current->accrual_authority = $accrualAuthority;
                }
                $current->save();
                if ($publish) {
                    $this->supersedeOthers($locked, $current);
                    $this->discardOtherDrafts($locked, $current);
                    $locked->forceFill(['active_plan_version_id' => $current->id])->save();
                    $this->syncCatalogFlagsFromPlan($locked, $current);
                }
                $this->audit($locked, $current, $admin, $publish ? 'plan_published' : 'plan_draft_updated', $before, $current->fresh()->toArray(), $ip);

                return $current->fresh();
            }

            // No draft: insert. With approved purchases, publishing always creates a new immutable version.
            $version = $this->insertVersion($locked, $plan, $admin, $status, $accrualAuthority);
            if ($publish) {
                $this->supersedeOthers($locked, $version);
                $this->discardOtherDrafts($locked, $version);
                $locked->forceFill(['active_plan_version_id' => $version->id])->save();
                $this->syncCatalogFlagsFromPlan($locked, $version);
            }
            $action = $publish
                ? ($hasApproved ? 'plan_published_new_version' : 'plan_published')
                : 'plan_draft_created';
            $this->audit($locked, $version, $admin, $action, null, $version->toArray(), $ip);

            return $version;
        });
    }

    public function setActive(Package $package, bool $active, User $admin, ?string $ip = null): Package
    {
        return DB::transaction(function () use ($package, $active, $admin, $ip): Package {
            $locked = Package::query()->whereKey($package->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshotPackage($locked);
            if ($active && $this->typeWantsRoi((string) $locked->package_type)) {
                $this->assertActiveRoiReady($locked);
            }
            $locked->is_active = $active;
            if ($active) {
                $locked->purchase_available = true;
            }
            $locked->save();
            $this->audit($locked, null, $admin, $active ? 'package_activated' : 'package_deactivated', $before, $this->snapshotPackage($locked), $ip);
            $this->activity($admin, $active ? 'package_activated' : 'package_deactivated', $locked->code);

            return $locked->fresh();
        });
    }

    public function resolveVersionForNewPurchase(Package $package): ?PackagePlanVersion
    {
        return $this->resolver->activeVersionForPackage($package);
    }

    /**
     * Plan row for Admin edit fields: prefer the latest draft, else the active published plan.
     * Never invents ROI amounts.
     */
    public function editingPlanVersion(Package $package): ?PackagePlanVersion
    {
        $draft = PackagePlanVersion::query()
            ->where('package_id', $package->id)
            ->where('status', PackagePlanVersion::STATUS_DRAFT)
            ->orderByDesc('version_number')
            ->orderByDesc('id')
            ->first();

        if ($draft !== null) {
            return $draft;
        }

        return $this->resolver->activeVersionForPackage($package);
    }

    /**
     * Explicit income-source cutover. Never called implicitly from plan publish.
     * Does not rewrite purchases, snapshots, wallets, or plan version rows.
     */
    public function setIncomeSource(Package $package, string $incomeSource, bool $confirmed, User $admin, ?string $ip = null): Package
    {
        $allowed = [Package::INCOME_SOURCE_LEGACY, Package::INCOME_SOURCE_DATABASE];
        if (! in_array($incomeSource, $allowed, true)) {
            throw ValidationException::withMessages([
                'income_source' => 'Income source must be Legacy Config or Database Plan Version.',
            ]);
        }

        return DB::transaction(function () use ($package, $incomeSource, $confirmed, $admin, $ip): Package {
            $locked = Package::query()->whereKey($package->id)->lockForUpdate()->firstOrFail();
            $current = (string) ($locked->income_source ?: Package::INCOME_SOURCE_LEGACY);
            if ($current === $incomeSource) {
                return $locked;
            }

            if (! $confirmed) {
                throw ValidationException::withMessages([
                    'confirm_income_source_change' => 'Confirm the income source change before switching. Historical purchases stay on their snapshots.',
                ]);
            }

            if ($incomeSource === Package::INCOME_SOURCE_DATABASE) {
                $version = $this->resolver->activeVersionForPackage($locked);
                if ($version === null || ! $version->has_daily_roi) {
                    // Allow switch for tour-only / ROI-off packages; catalog ROI may remain unconfigured until a plan is published.
                }
            }

            $before = $this->snapshotPackage($locked);
            $locked->income_source = $incomeSource;
            $locked->save();
            $this->audit($locked, $locked->activePlanVersion, $admin, 'income_source_changed', $before, $this->snapshotPackage($locked), $ip);
            $this->activity($admin, 'package_income_source_changed', $locked->code.' → '.$incomeSource);

            return $locked->fresh();
        });
    }

    /**
     * @return array{
     *   catalog_active: bool,
     *   purchase_available: bool,
     *   roi_eligible: bool,
     *   income_configured: bool,
     *   income_source: string,
     *   stored_income_source: string,
     *   resolved_income_source: string|null,
     *   published_plan_version: string|null,
     *   published_plan_version_id: int|null,
     *   accrual_authority: bool,
     *   posting_authorized: bool,
     *   posting_note: string,
     *   associate_posting: bool
     * }
     */
    public function statusIndicators(Package $package): array
    {
        $definition = $this->resolver->forPackageCode((string) $package->code);
        $version = $this->resolver->activeVersionForPackage($package);
        $postingAuthorized = (bool) config('deltasupport.package_income.tour_posting_authorized', false)
            || (bool) config('deltasupport.package_income.enabled', false);
        $stored = (string) ($package->income_source ?: Package::INCOME_SOURCE_LEGACY);

        return [
            'catalog_active' => (bool) $package->is_active,
            'purchase_available' => (bool) ($package->purchase_available ?? true),
            'roi_eligible' => (bool) $package->roi_eligible,
            'income_configured' => $definition !== null,
            'income_source' => (string) ($definition['source'] ?? $stored),
            'stored_income_source' => $stored,
            'resolved_income_source' => $definition['source'] ?? null,
            'published_plan_version' => $version ? 'v'.$version->version_number : null,
            'published_plan_version_id' => $version?->id,
            'accrual_authority' => (bool) ($version?->accrual_authority ?? false),
            'posting_authorized' => $postingAuthorized,
            'posting_note' => $package->is_active && ! $postingAuthorized
                ? 'Package may be Active for purchase, but income posting is not authorized.'
                : ($postingAuthorized ? 'Financial posting flags require separate controls review.' : 'Financial posting is disabled.'),
            'associate_posting' => (bool) config('deltasupport.associate_commission.posting_enabled'),
        ];
    }

    /**
     * Seed draft Original Tour Plan versions for known codes without enabling accrual authority.
     *
     * @param  array<string, array<string, mixed>>  $plansByCode
     */
    public function seedInactivePlanDrafts(array $plansByCode, ?User $admin = null): int
    {
        $count = 0;
        foreach ($plansByCode as $code => $plan) {
            $package = Package::query()->where('code', $code)->first();
            if ($package === null) {
                continue;
            }
            $exists = PackagePlanVersion::query()->where('package_id', $package->id)->exists();
            if ($exists) {
                continue;
            }
            $adminUser = $admin ?? User::query()->where('is_admin', true)->first();
            if ($adminUser === null) {
                continue;
            }
            $this->insertVersion($package, $plan + ['accrual_authority' => false, 'label' => 'original_tour_plan_v1_draft'], $adminUser, PackagePlanVersion::STATUS_DRAFT, false);
            $count++;
        }

        return $count;
    }

    private function defaultIncomeSource(string $code): string
    {
        if (isset(config('deltasupport.package_income.packages')[$code])) {
            return 'legacy_config';
        }

        return 'database';
    }

    private function typeWantsRoi(string $type): bool
    {
        return in_array($type, [Package::TYPE_TOUR_ROI, Package::TYPE_PREMIUM, Package::TYPE_ROI], true);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function assertRoiPlan(array $plan, bool $wantsRoi): void
    {
        $hasRoi = (bool) ($plan['has_daily_roi'] ?? false);
        if (! $wantsRoi || ! $hasRoi) {
            if ($hasRoi && ! $wantsRoi) {
                throw ValidationException::withMessages([
                    'has_daily_roi' => 'Tour Only packages cannot have Daily ROI.',
                ]);
            }

            return;
        }

        $daily = (string) ($plan['roi_daily'] ?? '');
        $days = (int) ($plan['roi_days'] ?? 0);
        if (! preg_match('/^\d{1,12}(\.\d{1,4})?$/', $daily) || bccomp($daily, '0', 4) <= 0) {
            throw ValidationException::withMessages([
                'roi_daily' => 'Enter a positive Daily ROI amount with up to 4 decimals.',
            ]);
        }
        if ($days < 1 || $days > 100000) {
            throw ValidationException::withMessages([
                'roi_days' => 'ROI duration must be between 1 and 100000 days.',
            ]);
        }
        if (isset($plan['commission_pool']) && $plan['commission_pool'] !== null && $plan['commission_pool'] !== '') {
            if (! preg_match('/^\d{1,12}(\.\d{1,4})?$/', (string) $plan['commission_pool'])) {
                throw ValidationException::withMessages([
                    'commission_pool' => 'Legacy commission pool must use up to 4 decimals.',
                ]);
            }
        }
    }

    private function assertActiveRoiReady(Package $package): void
    {
        if (! $package->roi_eligible) {
            throw ValidationException::withMessages([
                'is_active' => 'ROI packages require ROI eligibility before activation.',
            ]);
        }

        $definition = $this->resolver->forPackageCode((string) $package->code);
        $version = $this->resolver->activeVersionForPackage($package);
        if ($definition === null && ($version === null || ! $version->has_daily_roi)) {
            // Allow activation when a draft/published plan exists with ROI fields even if accrual_authority is off
            // only if income_source is database and version has ROI — still require configured ROI numbers.
            if ($version === null || ! $version->has_daily_roi || $version->roi_daily === null || $version->roi_days === null) {
                throw ValidationException::withMessages([
                    'is_active' => 'Configure and publish Daily ROI terms before activating an ROI package.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function insertVersion(Package $package, array $plan, User $admin, string $status, bool $accrualAuthority): PackagePlanVersion
    {
        $next = (int) PackagePlanVersion::query()->where('package_id', $package->id)->max('version_number') + 1;
        $version = new PackagePlanVersion([
            'package_id' => $package->id,
            'version_number' => $next,
            'status' => $status,
            'created_by' => $admin->id,
        ]);
        $this->fillVersion($version, $plan, $accrualAuthority);
        if ($status === PackagePlanVersion::STATUS_PUBLISHED) {
            $version->published_at = now();
            $version->published_by = $admin->id;
            $version->accrual_authority = $accrualAuthority;
        }
        $version->save();

        return $version;
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function fillVersion(PackagePlanVersion $version, array $plan, bool $accrualAuthority): void
    {
        $hasRoi = (bool) ($plan['has_daily_roi'] ?? false);
        $version->has_daily_roi = $hasRoi;
        $version->roi_daily = $hasRoi ? bcadd((string) $plan['roi_daily'], '0', 4) : null;
        $version->roi_days = $hasRoi ? (int) $plan['roi_days'] : null;
        $version->wait_days = isset($plan['wait_days']) && $plan['wait_days'] !== '' && $plan['wait_days'] !== null
            ? (int) $plan['wait_days']
            : null;
        $version->roi_start_rule = $plan['roi_start_rule'] ?? ($hasRoi ? 'approval_plus_wait_days' : null);
        $version->roi_end_rule = $plan['roi_end_rule'] ?? ($hasRoi ? 'first_accrual_plus_duration_minus_one' : null);
        $version->commission_pool = isset($plan['commission_pool']) && $plan['commission_pool'] !== '' && $plan['commission_pool'] !== null
            ? bcadd((string) $plan['commission_pool'], '0', 4)
            : null;
        $version->commission_days = isset($plan['commission_days']) && $plan['commission_days'] !== '' && $plan['commission_days'] !== null
            ? (int) $plan['commission_days']
            : null;
        $version->level_commission_eligible = (bool) ($plan['level_commission_eligible'] ?? false);
        $version->tour_eligible_after_days = $this->nullableInt($plan, 'tour_eligible_after_days');
        $version->tour_nights = $this->nullableInt($plan, 'tour_nights');
        $version->tour_days = $this->nullableInt($plan, 'tour_days');
        $version->tour_adults = $this->nullableInt($plan, 'tour_adults');
        $version->tour_children = $this->nullableInt($plan, 'tour_children');
        $version->food_coupon_amount = isset($plan['food_coupon_amount']) && $plan['food_coupon_amount'] !== '' && $plan['food_coupon_amount'] !== null
            ? bcadd((string) $plan['food_coupon_amount'], '0', 4)
            : null;
        $version->food_coupon_currency = $plan['food_coupon_currency'] ?? null;
        $version->tour_description = $plan['tour_description'] ?? null;
        $version->label = $plan['label'] ?? null;
        $version->change_note = $plan['change_note'] ?? null;
        $version->accrual_authority = $accrualAuthority;
    }

    private function supersedeOthers(Package $package, PackagePlanVersion $keep): void
    {
        PackagePlanVersion::query()
            ->where('package_id', $package->id)
            ->where('id', '!=', $keep->id)
            ->where('status', PackagePlanVersion::STATUS_PUBLISHED)
            ->update(['status' => PackagePlanVersion::STATUS_SUPERSEDED]);
    }

    /**
     * Drop leftover drafts after a publish so the edit form loads the published ROI, not a stale draft.
     */
    private function discardOtherDrafts(Package $package, PackagePlanVersion $keep): void
    {
        PackagePlanVersion::query()
            ->where('package_id', $package->id)
            ->where('id', '!=', $keep->id)
            ->where('status', PackagePlanVersion::STATUS_DRAFT)
            ->delete();
    }

    private function syncCatalogFlagsFromPlan(Package $package, PackagePlanVersion $version): void
    {
        $package->forceFill([
            'roi_eligible' => $version->has_daily_roi,
            'level_commission_eligible' => $version->level_commission_eligible,
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotPackage(Package $package): array
    {
        return $package->only([
            'id', 'code', 'name', 'package_type', 'price_usd', 'is_active', 'purchase_available',
            'roi_eligible', 'auto_roi_enabled', 'level_commission_eligible', 'income_source',
            'active_plan_version_id', 'sort_order',
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function audit(Package $package, ?PackagePlanVersion $version, User $admin, string $action, ?array $before, ?array $after, ?string $ip): void
    {
        if (! Schema::hasTable('package_plan_audits')) {
            return;
        }

        PackagePlanAudit::query()->create([
            'package_id' => $package->id,
            'package_plan_version_id' => $version?->id,
            'user_id' => $admin->id,
            'action' => $action,
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => $ip,
        ]);
    }

    private function activity(User $admin, string $action, string $code): void
    {
        AdminActivityLog::query()->create([
            'user_id' => $admin->id,
            'action' => $action,
            'subject' => 'package:'.$code,
            'format' => 'admin',
            'scope' => 'packages',
            'filters' => ['package_code' => $code],
            'row_count' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function nullableInt(array $plan, string $key): ?int
    {
        if (! array_key_exists($key, $plan) || $plan[$key] === null || $plan[$key] === '') {
            return null;
        }

        return (int) $plan[$key];
    }
}
