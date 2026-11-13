<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\Package;
use App\Models\PackageIncomeActivation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Dedicated DST-TOUR-003 forward-only activation. Never arms posting or credits wallets.
 */
class TourPackageIncomeActivation
{
    public const CONFIRM = 'activate-tour-package-income';

    public const PACKAGE_CODE = PackageIncomeActivationGate::TOUR_CODE;

    public function __construct(
        private readonly PackageIncomePolicy $policy,
        private readonly PackageIncomeActivationGate $gate,
    ) {
    }

    /**
     * @return array{
     *   database: string,
     *   package_code: string,
     *   requested_date: string,
     *   existing_activation: ?string,
     *   terms: array{roi_daily: string, roi_days: int, commission_pool: string, commission_days: ?int}|null,
     *   package: ?array{type: string, roi_eligible: int, level_commission_eligible: int, price: string},
     *   planned_changes: list<array{key: string, before: string, after: string, needed: bool}>,
     *   needed_count: int,
     *   duration_rule: string
     * }
     */
    public function preview(string $activationBusinessDate): array
    {
        $date = $this->parseDate($activationBusinessDate);
        $existing = $this->gate->activationBusinessDate(self::PACKAGE_CODE);
        $terms = $this->policy->definition(self::PACKAGE_CODE);
        $package = Package::query()->where('code', self::PACKAGE_CODE)->first();

        $planned = [];
        $planned[] = [
            'key' => 'package_income_activations.'.self::PACKAGE_CODE,
            'before' => $existing?->toDateString() ?? '(missing)',
            'after' => $date->toDateString(),
            'needed' => $existing === null,
        ];

        if ($package === null) {
            $planned[] = [
                'key' => 'packages.'.self::PACKAGE_CODE,
                'before' => '(missing)',
                'after' => Package::TYPE_PREMIUM.' / roi=1 / level=1',
                'needed' => true,
            ];
        } else {
            $planned[] = [
                'key' => self::PACKAGE_CODE.'.package_type',
                'before' => (string) $package->package_type,
                'after' => Package::TYPE_PREMIUM,
                'needed' => (string) $package->package_type !== Package::TYPE_PREMIUM,
            ];
            $planned[] = [
                'key' => self::PACKAGE_CODE.'.roi_eligible',
                'before' => (string) (int) $package->roi_eligible,
                'after' => '1',
                'needed' => (int) $package->roi_eligible !== 1,
            ];
            $planned[] = [
                'key' => self::PACKAGE_CODE.'.level_commission_eligible',
                'before' => (string) (int) $package->level_commission_eligible,
                'after' => '1',
                'needed' => (int) $package->level_commission_eligible !== 1,
            ];
        }

        return [
            'database' => (string) DB::connection()->getDatabaseName(),
            'package_code' => self::PACKAGE_CODE,
            'requested_date' => $date->toDateString(),
            'existing_activation' => $existing?->toDateString(),
            'terms' => $terms,
            'package' => $package === null ? null : [
                'type' => (string) $package->package_type,
                'roi_eligible' => (int) $package->roi_eligible,
                'level_commission_eligible' => (int) $package->level_commission_eligible,
                'price' => (string) $package->price_usd,
            ],
            'planned_changes' => $planned,
            'needed_count' => count(array_filter($planned, fn (array $row): bool => $row['needed'])),
            'duration_rule' => 'ROI/commission windows stay anchored to each purchase original first accrual (approval + wait_days). Activation is a posting floor only; duration is not restarted.',
        ];
    }

    /**
     * @return array{activation_business_date: string, applied: list<array{key: string, before: string, after: string}>}
     */
    public function activate(string $activationBusinessDate): array
    {
        if (! Schema::hasTable('package_income_activations')) {
            throw new RuntimeException('package_income_activations table is missing. Run migrations first. No change was made.');
        }

        $preview = $this->preview($activationBusinessDate);
        if ($preview['terms'] === null) {
            throw new RuntimeException(self::PACKAGE_CODE.' package_income config is missing. Deploy the income map first. No change was made.');
        }
        if (bccomp($preview['terms']['roi_daily'], '2.5000', 4) !== 0
            || (int) $preview['terms']['roi_days'] !== 1000
            || bccomp($preview['terms']['commission_pool'], '2.4000', 4) !== 0
            || (int) $preview['terms']['commission_days'] !== 400) {
            throw new RuntimeException(self::PACKAGE_CODE.' config terms do not match the approved premium schedule. No change was made.');
        }
        if ($preview['package'] === null) {
            throw new RuntimeException(self::PACKAGE_CODE.' package row is missing. No change was made.');
        }
        if ($preview['existing_activation'] !== null) {
            if ($preview['existing_activation'] !== $preview['requested_date']) {
                throw new RuntimeException(
                    self::PACKAGE_CODE.' is already activated for '.$preview['existing_activation']
                    .'. The activation date cannot be silently changed. No change was made.'
                );
            }
            if ($preview['needed_count'] === 0) {
                return [
                    'activation_business_date' => $preview['existing_activation'],
                    'applied' => [],
                ];
            }
        }

        $applied = [];
        $date = $preview['requested_date'];

        try {
            DB::transaction(function () use ($preview, $date, &$applied): void {
                foreach ($preview['planned_changes'] as $change) {
                    if (! $change['needed']) {
                        continue;
                    }

                    if ($change['key'] === 'package_income_activations.'.self::PACKAGE_CODE) {
                        if (PackageIncomeActivation::query()->where('package_code', self::PACKAGE_CODE)->exists()) {
                            throw new RuntimeException(self::PACKAGE_CODE.' activation row already exists. No change was kept.');
                        }
                        PackageIncomeActivation::query()->create([
                            'package_code' => self::PACKAGE_CODE,
                            'activation_business_date' => $date,
                            'activated_at' => now(),
                            'activated_by' => 'artisan:financial:activate-tour-income',
                            'confirm_token' => self::CONFIRM,
                            'terms_snapshot' => $preview['terms'],
                        ]);
                    }

                    if ($change['key'] === self::PACKAGE_CODE.'.package_type') {
                        $updated = DB::table('packages')->where('code', self::PACKAGE_CODE)->update([
                            'package_type' => Package::TYPE_PREMIUM,
                            'updated_at' => now(),
                        ]);
                        if ($updated !== 1) {
                            throw new RuntimeException('package_type could not be updated. No change was kept.');
                        }
                    }

                    if ($change['key'] === self::PACKAGE_CODE.'.roi_eligible') {
                        $updated = DB::table('packages')->where('code', self::PACKAGE_CODE)->update([
                            'roi_eligible' => true,
                            'updated_at' => now(),
                        ]);
                        if ($updated !== 1) {
                            throw new RuntimeException('roi_eligible could not be updated. No change was kept.');
                        }
                    }

                    if ($change['key'] === self::PACKAGE_CODE.'.level_commission_eligible') {
                        $updated = DB::table('packages')->where('code', self::PACKAGE_CODE)->update([
                            'level_commission_eligible' => true,
                            'updated_at' => now(),
                        ]);
                        if ($updated !== 1) {
                            throw new RuntimeException('level_commission_eligible could not be updated. No change was kept.');
                        }
                    }

                    $applied[] = [
                        'key' => $change['key'],
                        'before' => $change['before'],
                        'after' => $change['after'],
                    ];
                }

                $activation = $this->gate->activationBusinessDate(self::PACKAGE_CODE);
                if ($activation === null || $activation->toDateString() !== $date) {
                    throw new RuntimeException('Post-activation verification failed. No change was kept.');
                }
                $package = Package::query()->where('code', self::PACKAGE_CODE)->first();
                if ($package === null
                    || (string) $package->package_type !== Package::TYPE_PREMIUM
                    || ! $package->roi_eligible
                    || ! $package->level_commission_eligible) {
                    throw new RuntimeException('Package flags were not activated. No change was kept.');
                }
            });
        } catch (Throwable $exception) {
            Log::error('Tour package income activation failed: '.$exception->getMessage(), [
                'requested_date' => $date,
            ]);
            throw $exception;
        }

        Log::info('Tour package income activation applied.', [
            'package_code' => self::PACKAGE_CODE,
            'activation_business_date' => $date,
            'applied' => $applied,
        ]);
        $this->audit($date, $applied);

        return [
            'activation_business_date' => $date,
            'applied' => $applied,
        ];
    }

    private function parseDate(string $activationBusinessDate): CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $activationBusinessDate)) {
            throw new RuntimeException('Activation date must be YYYY-MM-DD Asia/Kolkata. No change was made.');
        }

        return CarbonImmutable::parse($activationBusinessDate, $this->gate->timezone())->startOfDay();
    }

    /**
     * @param  list<array{key: string, before: string, after: string}>  $applied
     */
    private function audit(string $date, array $applied): void
    {
        $adminId = User::query()->where('is_admin', true)->value('id');
        if (! $adminId) {
            return;
        }

        AdminActivityLog::query()->create([
            'user_id' => $adminId,
            'action' => 'tour_package_income_activated',
            'subject' => self::PACKAGE_CODE,
            'format' => null,
            'scope' => 'admin',
            'filters' => [
                'database' => (string) DB::connection()->getDatabaseName(),
                'activation_business_date' => $date,
                'applied' => $applied,
                'duration_rule' => 'original_first_accrual_window_with_activation_floor',
            ],
            'row_count' => count($applied),
        ]);
    }
}
