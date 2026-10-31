<?php

namespace App\Models;

use App\Support\MoneyDisplay;
use Illuminate\Database\Eloquent\Model;

class WithdrawalSetting extends Model
{
    public const CHARGE_TYPES = ['none', 'flat', 'percent'];

    public const WALLETS = ['roi', 'working', 'activation'];

    protected $fillable = [
        'minimum_amount',
        'maximum_amount',
        'charge_type',
        'charge_value',
        'daily_limit',
        'monthly_limit',
        'eligible_wallets',
        'supported_networks',
        'address_validation_policy',
        'member_cancellation_policy',
        'submissions_enabled',
        'denomination',
    ];

    protected $casts = [
        'minimum_amount' => 'decimal:4',
        'maximum_amount' => 'decimal:4',
        'charge_value' => 'decimal:4',
        'daily_limit' => 'decimal:4',
        'monthly_limit' => 'decimal:4',
        'eligible_wallets' => 'array',
        'supported_networks' => 'array',
        'submissions_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $settings): void {
            $settings->denomination = MoneyDisplay::USDT;
            if ($settings->submissions_enabled && $settings->configurationErrors() !== []) {
                $settings->submissions_enabled = false;
            }
        });
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate([], [
            'minimum_amount' => '20.0000',
            'denomination' => MoneyDisplay::USDT,
            'submissions_enabled' => false,
        ]);
    }

    public function isReady(): bool
    {
        return (bool) $this->submissions_enabled && $this->configurationErrors() === [];
    }

    /**
     * @return list<string>
     */
    public function configuredWallets(): array
    {
        return array_values(array_intersect(
            self::WALLETS,
            array_values(array_filter((array) $this->eligible_wallets, 'is_string'))
        ));
    }

    /**
     * @return list<string>
     */
    public function configuredNetworks(): array
    {
        return array_values(array_filter(
            (array) $this->supported_networks,
            fn (mixed $network): bool => is_string($network)
                && trim($network) !== ''
                && ! PaymentSetting::isPlaceholder($network)
                && ! PaymentSetting::containsSecret($network)
        ));
    }

    /**
     * Rules required before an administrator can turn submissions on.
     * Daily limits, address policy, and cancellation policy stay in the
     * database for older rows and are not required.
     *
     * @return array<string, string>
     */
    public function configurationErrors(): array
    {
        $errors = [];

        if (strcasecmp((string) $this->denomination, MoneyDisplay::USDT) !== 0) {
            $errors['denomination'] = 'Withdrawal amounts must use USDT.';
        }
        if ($this->minimum_amount === null || bccomp((string) $this->minimum_amount, '0', 4) <= 0) {
            $errors['minimum_amount'] = 'Enter the minimum withdrawal in USDT.';
        }
        if ($this->maximum_amount === null || bccomp((string) $this->maximum_amount, '0', 4) <= 0) {
            $errors['maximum_amount'] = 'Enter the maximum withdrawal in USDT.';
        } elseif ($this->minimum_amount !== null && bccomp((string) $this->maximum_amount, (string) $this->minimum_amount, 4) < 0) {
            $errors['maximum_amount'] = 'The maximum must be at least the minimum withdrawal.';
        }
        if (! in_array($this->charge_type, self::CHARGE_TYPES, true)) {
            $errors['charge_type'] = 'Choose a withdrawal charge before submissions can be enabled.';
        } elseif ($this->charge_type === 'none' && $this->charge_value !== null && bccomp((string) $this->charge_value, '0', 4) !== 0) {
            $errors['charge_value'] = 'A charge value does not apply when the charge type is none.';
        } elseif (in_array($this->charge_type, ['flat', 'percent'], true) && ($this->charge_value === null || bccomp((string) $this->charge_value, '0', 4) <= 0)) {
            $errors['charge_value'] = 'Enter the charge value.';
        } elseif ($this->charge_type === 'percent' && bccomp((string) $this->charge_value, '100', 4) > 0) {
            $errors['charge_value'] = 'A percentage charge cannot exceed 100.';
        } elseif ($this->charge_type === 'flat' && $this->minimum_amount !== null && bccomp((string) $this->charge_value, (string) $this->minimum_amount, 4) >= 0) {
            $errors['charge_value'] = 'A flat charge must be less than the minimum withdrawal.';
        }

        $wallets = array_values(array_filter((array) $this->eligible_wallets, 'is_string'));
        if ($wallets === [] || array_diff($wallets, self::WALLETS) !== []) {
            $errors['eligible_wallets'] = 'Select at least one eligible source wallet.';
        }

        $networks = $this->configuredNetworks();
        if ($networks === []) {
            $errors['network'] = 'Enter one real blockchain network. A placeholder network cannot enable submissions.';
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    public function enablementErrors(): array
    {
        return $this->configurationErrors();
    }
}
