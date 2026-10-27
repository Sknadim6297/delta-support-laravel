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
            $settings->submissions_enabled = false;
            $settings->denomination = MoneyDisplay::USDT;
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
        return false;
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
     * @return array<string, string>
     */
    public function enablementErrors(): array
    {
        $errors = [];

        if (strcasecmp((string) $this->denomination, MoneyDisplay::USDT) !== 0) {
            $errors['denomination'] = 'Withdrawal amounts must use USDT.';
        }
        if ($this->minimum_amount === null || bccomp((string) $this->minimum_amount, '0', 4) <= 0) {
            $errors['minimum_amount'] = 'Enter the minimum withdrawal in USDT.';
        }
        if (! in_array($this->charge_type, self::CHARGE_TYPES, true)) {
            $errors['charge_type'] = 'Choose an approved withdrawal charge before submissions can be enabled.';
        } elseif ($this->charge_type === 'none' && $this->charge_value !== null && bccomp((string) $this->charge_value, '0', 4) !== 0) {
            $errors['charge_value'] = 'A charge value does not apply when the charge type is none.';
        } elseif (in_array($this->charge_type, ['flat', 'percent'], true) && ($this->charge_value === null || bccomp((string) $this->charge_value, '0', 4) <= 0)) {
            $errors['charge_value'] = 'Enter the approved charge value.';
        }
        if ($this->daily_limit === null || bccomp((string) $this->daily_limit, '0', 4) <= 0) {
            $errors['daily_limit'] = 'Enter the approved daily limit. An unset limit cannot enable submissions.';
        }
        if ($this->monthly_limit === null || bccomp((string) $this->monthly_limit, '0', 4) <= 0) {
            $errors['monthly_limit'] = 'Enter the approved monthly limit. An unset limit cannot enable submissions.';
        }

        $wallets = array_values(array_filter((array) $this->eligible_wallets, 'is_string'));
        if ($wallets === [] || array_diff($wallets, self::WALLETS) !== []) {
            $errors['eligible_wallets'] = 'Select the wallets that are eligible. This choice is still unset.';
        }

        $networks = array_values(array_filter((array) $this->supported_networks, 'is_string'));
        if ($networks === [] || collect($networks)->contains(fn (string $network): bool => PaymentSetting::isPlaceholder($network) || PaymentSetting::containsSecret($network))) {
            $errors['supported_networks'] = 'Enter at least one real blockchain network. A placeholder network cannot enable submissions.';
        }

        $addressPolicy = trim((string) $this->address_validation_policy);
        if ($addressPolicy === '' || PaymentSetting::containsSecret($addressPolicy)) {
            $errors['address_validation_policy'] = 'Enter the address validation policy. Do not store a private key or seed phrase.';
        }

        $cancellation = trim((string) $this->member_cancellation_policy);
        if ($cancellation === '' || PaymentSetting::containsSecret($cancellation)) {
            $errors['member_cancellation_policy'] = 'Enter the member cancellation policy.';
        }

        $errors['submissions_enabled'] = 'Withdrawal submissions cannot be enabled in this phase.';

        return $errors;
    }
}
