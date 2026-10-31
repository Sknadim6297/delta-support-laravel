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
            'eligible_wallets' => ['nullable', 'array'],
            'eligible_wallets.*' => ['in:roi,working,activation'],
            'network' => ['nullable', 'string', 'max:80'],
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
        if ($maximum !== null && bccomp($maximum, $minimum, 4) < 0) {
            throw ValidationException::withMessages([
                'maximum_amount' => 'The maximum must be at least the minimum withdrawal.',
            ]);
        }

        $network = $this->network($data['network'] ?? null);
        if ($network !== null && PaymentSetting::containsSecret($network)) {
            throw ValidationException::withMessages([
                'network' => 'Do not store a private key or seed phrase.',
            ]);
        }

        $settings = WithdrawalSetting::current();
        $settings->fill([
            'minimum_amount' => $minimum,
            'maximum_amount' => $maximum,
            'charge_type' => $chargeType,
            'charge_value' => $chargeValue,
            'eligible_wallets' => array_values($data['eligible_wallets'] ?? []) ?: null,
            'supported_networks' => $network !== null ? [$network] : null,
            'submissions_enabled' => $request->boolean('submissions_enabled'),
            'denomination' => MoneyDisplay::USDT,
        ]);

        if ($settings->submissions_enabled) {
            $errors = $settings->configurationErrors();
            if ($errors !== []) {
                $errors['submissions_enabled'] = 'Complete the withdrawal rules before enabling submissions.';
                throw ValidationException::withMessages($errors);
            }
        }

        $settings->save();

        return redirect()->route('admin.withdrawals.settings')
            ->with('success', $settings->submissions_enabled
                ? 'Withdrawal settings saved. Members can submit requests. Funds stay reserved until you approve or reject them. No payout is sent automatically.'
                : 'Withdrawal settings saved. Withdrawal submissions are disabled.');
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

    private function network(?string $value): ?string
    {
        $network = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
        if ($network === '') {
            return null;
        }
        if (PaymentSetting::isPlaceholder($network)) {
            throw ValidationException::withMessages([
                'network' => 'Enter a real blockchain network. A placeholder network cannot be saved.',
            ]);
        }
        if (strlen($network) > 80) {
            throw ValidationException::withMessages([
                'network' => 'The network name must be 80 characters or fewer.',
            ]);
        }

        return $network;
    }
}
