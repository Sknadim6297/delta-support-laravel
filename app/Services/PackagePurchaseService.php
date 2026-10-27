<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\PaymentDecision;
use App\Models\PaymentSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PackagePurchaseService
{
    public function submit(Member $member, Package $package, string $paymentReference, string $idempotencyKey): PackagePurchase
    {
        $existing = PackagePurchase::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            abort_unless($existing->member_id === $member->member_id, 403);

            return $existing;
        }

        $settings = PaymentSetting::current();
        if (! $settings->isReady()) {
            throw ValidationException::withMessages([
                'package' => 'Payment instructions are not available yet.',
            ]);
        }

        if (! $package->is_active) {
            throw ValidationException::withMessages([
                'package' => 'This package is not available.',
            ]);
        }

        $reference = trim($paymentReference);
        $this->assertReferenceShape($settings, $reference);

        return DB::transaction(function () use ($member, $package, $settings, $reference, $idempotencyKey): PackagePurchase {
            $lockedPackage = Package::query()->whereKey($package->id)->lockForUpdate()->firstOrFail();
            if (! $lockedPackage->is_active) {
                throw ValidationException::withMessages([
                    'package' => 'This package is not available.',
                ]);
            }

            $duplicate = PackagePurchase::query()
                ->where('network', $settings->network)
                ->where('payment_reference', $reference)
                ->lockForUpdate()
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'payment_reference' => 'This transaction reference has already been submitted.',
                ]);
            }

            return PackagePurchase::query()->create([
                'reference' => $this->uniqueCode('PUR'),
                'idempotency_key' => $idempotencyKey,
                'member_id' => $member->member_id,
                'package_id' => $lockedPackage->id,
                'package_code' => $lockedPackage->code,
                'package_name' => $lockedPackage->name,
                'price_usd' => $lockedPackage->price_usd,
                'tour_title' => $lockedPackage->tour_title,
                'tour_details' => $lockedPackage->tour_details,
                'duration' => $lockedPackage->duration,
                'benefits' => $lockedPackage->benefits,
                'currency_label' => $settings->currency_label,
                'network' => $settings->network,
                'payment_reference' => $reference,
                'status' => PackagePurchase::PENDING,
                'submitted_at' => now(),
            ]);
        });
    }

    public function approve(PackagePurchase $purchase, User $admin): Invoice
    {
        return DB::transaction(function () use ($purchase, $admin): Invoice {
            $locked = PackagePurchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $existing = Invoice::query()->where('package_purchase_id', $locked->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            if ($locked->status !== PackagePurchase::PENDING) {
                throw ValidationException::withMessages([
                    'purchase' => 'Only a pending payment can be approved.',
                ]);
            }

            $company = CompanySetting::current();
            $member = Member::query()->where('member_id', $locked->member_id)->first();
            $memberName = (string) ($member?->member_name ?? '');
            if ($member && $member->status !== 'active') {
                $member->update(['status' => 'active']);
            }
            $approvedAt = now();
            $locked->forceFill([
                'status' => PackagePurchase::APPROVED,
                'reviewed_at' => $approvedAt,
                'reviewed_by' => $admin->id,
                'rejection_reason' => null,
            ])->save();

            $invoice = Invoice::query()->create([
                'invoice_number' => $this->uniqueCode('DS-INV'),
                'package_purchase_id' => $locked->id,
                'member_id' => $locked->member_id,
                'member_name' => $memberName,
                'package_name' => $locked->package_name,
                'price_usd' => $locked->price_usd,
                'currency_label' => $locked->currency_label,
                'network' => $locked->network,
                'payment_reference' => $locked->payment_reference,
                'company_name' => $company->company_name,
                'company_address' => $company->address,
                'company_email' => $company->email,
                'company_phone' => $company->phone,
                'purchased_at' => $locked->submitted_at,
                'approved_at' => $approvedAt,
                'status' => 'approved',
            ]);

            PaymentDecision::query()->create([
                'package_purchase_id' => $locked->id,
                'user_id' => $admin->id,
                'action' => 'approved',
                'reason' => null,
            ]);

            return $invoice;
        });
    }

    public function reject(PackagePurchase $purchase, User $admin, string $reason): PackagePurchase
    {
        return DB::transaction(function () use ($purchase, $admin, $reason): PackagePurchase {
            $locked = PackagePurchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== PackagePurchase::PENDING || $locked->invoice()->exists()) {
                throw ValidationException::withMessages([
                    'purchase' => 'Only a pending payment can be rejected.',
                ]);
            }

            $locked->forceFill([
                'status' => PackagePurchase::REJECTED,
                'rejection_reason' => $reason,
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
            ])->save();

            PaymentDecision::query()->create([
                'package_purchase_id' => $locked->id,
                'user_id' => $admin->id,
                'action' => 'rejected',
                'reason' => $reason,
            ]);

            return $locked;
        });
    }

    private function assertReferenceShape(PaymentSetting $settings, string $reference): void
    {
        if (! preg_match('/^[A-Za-z0-9:_\-\.]{6,128}$/', $reference)) {
            throw ValidationException::withMessages([
                'payment_reference' => 'Enter the transaction reference using 6 to 128 letters, numbers, or . : _ -',
            ]);
        }

        $pattern = trim((string) $settings->reference_pattern);
        if ($pattern !== '' && @preg_match('/' . str_replace('/', '\/', $pattern) . '/', $reference) !== 1) {
            throw ValidationException::withMessages([
                'payment_reference' => 'The transaction reference does not match the configured payment format.',
            ]);
        }
    }

    private function uniqueCode(string $prefix): string
    {
        do {
            $code = $prefix . '-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (
            PackagePurchase::query()->where('reference', $code)->exists()
            || Invoice::query()->where('invoice_number', $code)->exists()
        );

        return $code;
    }
}
