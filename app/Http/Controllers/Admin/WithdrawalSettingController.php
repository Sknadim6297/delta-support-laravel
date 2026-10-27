<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use App\Models\WithdrawalSetting;
use App\Support\MoneyDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WithdrawalSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.withdrawals.settings', [
            'settings' => WithdrawalSetting::current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'minimum_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'maximum_amount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,4'],
            'charge_type' => ['nullable', 'in:none,flat,percent'],
            'charge_value' => ['nullable', 'numeric', 'gte:0', 'decimal:0,4'],
            'daily_limit' => ['nullable', 'numeric', 'gt:0', 'decimal:0,4'],
            'monthly_limit' => ['nullable', 'numeric', 'gt:0', 'decimal:0,4'],
            'eligible_wallets' => ['nullable', 'array'],
            'eligible_wallets.*' => ['in:roi,working,activation'],
            'networks' => ['nullable', 'string', 'max:2000'],
            'address_validation_policy' => ['nullable', 'string', 'max:5000'],
            'member_cancellation_policy' => ['nullable', 'string', 'max:5000'],
            'submissions_enabled' => ['nullable', 'boolean'],
        ]);

        $chargeType = $data['charge_type'] ?? null;
        $chargeValue = $this->blankToNull($data['charge_value'] ?? null);
        if ($chargeType === null && $chargeValue !== null) {
            throw ValidationException::withMessages([
                'charge_value' => 'Choose a charge type before entering a charge value.',
            ]);
        }
        if ($chargeType === 'none') {
            $chargeValue = null;
        }
        if ($chargeType === 'percent' && $chargeValue !== null && bccomp($chargeValue, '100', 4) > 0) {
            throw ValidationException::withMessages([
                'charge_value' => 'A percentage charge cannot exceed 100.',
            ]);
        }

        $minimum = $this->money($data['minimum_amount']);
        $maximum = $this->blankToNull($data['maximum_amount'] ?? null);
        $daily = $this->blankToNull($data['daily_limit'] ?? null);
        $monthly = $this->blankToNull($data['monthly_limit'] ?? null);
        if ($maximum !== null && bccomp($maximum, $minimum, 4) < 0) {
            throw ValidationException::withMessages([
                'maximum_amount' => 'The maximum must be at least the minimum withdrawal.',
            ]);
        }
        if ($daily !== null && $monthly !== null && bccomp($monthly, $daily, 4) < 0) {
            throw ValidationException::withMessages([
                'monthly_limit' => 'The monthly limit must be at least the daily limit.',
            ]);
        }

        $networks = $this->networks($data['networks'] ?? null);
        $policy = $this->textOrNull($data['address_validation_policy'] ?? null);
        $cancellation = $this->textOrNull($data['member_cancellation_policy'] ?? null);
        foreach ([
            'networks' => implode(' ', $networks),
            'address_validation_policy' => (string) $policy,
            'member_cancellation_policy' => (string) $cancellation,
        ] as $field => $value) {
            if (PaymentSetting::containsSecret($value)) {
                throw ValidationException::withMessages([
                    $field => 'Do not store a private key or seed phrase.',
                ]);
            }
        }

        $settings = WithdrawalSetting::current();
        $settings->fill([
            'minimum_amount' => $minimum,
            'maximum_amount' => $maximum,
            'charge_type' => $chargeType,
            'charge_value' => $chargeValue,
            'daily_limit' => $daily,
            'monthly_limit' => $monthly,
            'eligible_wallets' => array_values($data['eligible_wallets'] ?? []) ?: null,
            'supported_networks' => $networks !== [] ? $networks : null,
            'address_validation_policy' => $policy,
            'member_cancellation_policy' => $cancellation,
            'submissions_enabled' => false,
            'denomination' => MoneyDisplay::USDT,
        ]);

        if ($request->boolean('submissions_enabled')) {
            $errors = $settings->enablementErrors();
            if (isset($errors['supported_networks'])) {
                $errors['networks'] = $errors['supported_networks'];
            }
            throw ValidationException::withMessages($errors);
        }

        $settings->save();

        return redirect()->route('admin.withdrawals.settings')
            ->with('success', 'Withdrawal settings saved. Submissions, holds, and payouts remain disabled.');
    }

    private function textOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $this->money($value);
    }

    private function money(mixed $value): string
    {
        return bcadd((string) $value, '0', 4);
    }

    /**
     * @return list<string>
     */
    private function networks(?string $value): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $value) ?: [];
        $networks = [];
        foreach ($lines as $line) {
            $network = trim(preg_replace('/\s+/', ' ', $line) ?? '');
            if ($network === '' || in_array($network, $networks, true)) {
                continue;
            }
            if (PaymentSetting::isPlaceholder($network)) {
                throw ValidationException::withMessages([
                    'networks' => 'Enter a real blockchain network. A placeholder network cannot be saved.',
                ]);
            }
            if (strlen($network) > 80) {
                throw ValidationException::withMessages([
                    'networks' => 'Each network name must be 80 characters or fewer.',
                ]);
            }
            $networks[] = $network;
        }

        return $networks;
    }
}
