<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\PaymentDecision;
use App\Models\PaymentSetting;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
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
                'package' => 'Payment Currently Unavailable.',
            ]);
        }

        if (! $package->is_active) {
            throw ValidationException::withMessages([
                'package' => 'This package is not available.',
            ]);
        }

        $reference = trim($paymentReference);
        $this->assertReferenceShape($reference);

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
                'package_type' => $lockedPackage->package_type ?: Package::TYPE_TOUR,
                'price_usd' => $lockedPackage->price_usd,
                'denomination' => $lockedPackage->denomination ?: 'USD',
                'tour_title' => $lockedPackage->tour_title,
                'tour_details' => $lockedPackage->tour_details,
                'duration' => $lockedPackage->duration,
                'benefits' => $lockedPackage->benefits,
                'description' => $lockedPackage->description,
                'full_description' => $lockedPackage->full_description,
                'destinations' => $lockedPackage->destinations,
                'inclusions' => $lockedPackage->inclusions,
                'exclusions' => $lockedPackage->exclusions,
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
                'purchase_reference' => $locked->reference,
                'member_id' => $locked->member_id,
                'member_name' => $memberName,
                'package_name' => $locked->package_name,
                'package_code' => $locked->package_code,
                'package_details' => $this->packageDetails($locked),
                'price_usd' => $locked->price_usd,
                'denomination' => $locked->denomination ?: 'USD',
                'currency_label' => $locked->currency_label,
                'network' => $locked->network,
                'payment_reference' => $locked->payment_reference,
                'payment_method' => $locked->payment_method,
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

    public function recordForCustomer(Member $member, Package $package, User $admin, string $method, string $reference, string $idempotencyKey): Invoice
    {
        if (! array_key_exists($method, PackagePurchase::PAYMENT_METHODS)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Choose Cash, Bank Transfer, or USDT.',
            ]);
        }

        $reference = trim($reference);
        $this->assertAdminReference($reference);

        return DB::transaction(function () use ($member, $package, $admin, $method, $reference, $idempotencyKey): Invoice {
            $existing = PackagePurchase::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                return $this->completeExistingCustomerPurchase($existing, $member, $package, $admin, $method, $reference);
            }

            Member::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();
            $lockedPackage = Package::query()->whereKey($package->id)->lockForUpdate()->firstOrFail();
            if (! $lockedPackage->is_active) {
                throw ValidationException::withMessages([
                    'package_id' => 'Choose an active package.',
                ]);
            }

            $alreadyActive = PackagePurchase::query()
                ->where('member_id', $member->member_id)
                ->where('package_id', $lockedPackage->id)
                ->where('status', PackagePurchase::APPROVED)
                ->lockForUpdate()
                ->exists();
            if ($alreadyActive) {
                throw ValidationException::withMessages([
                    'package_id' => 'This customer already has an active purchase of this package.',
                ]);
            }

            $network = $this->networkFor($method);
            $duplicate = PackagePurchase::query()
                ->where('network', $network)
                ->where('payment_reference', $reference)
                ->lockForUpdate()
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'payment_reference' => 'This payment reference has already been recorded.',
                ]);
            }

            try {
                $purchase = PackagePurchase::query()->create([
                    'reference' => $this->uniqueCode('PUR'),
                    'idempotency_key' => $idempotencyKey,
                    'member_id' => $member->member_id,
                    'package_id' => $lockedPackage->id,
                    'package_code' => $lockedPackage->code,
                    'package_name' => $lockedPackage->name,
                    'package_type' => $lockedPackage->package_type ?: Package::TYPE_TOUR,
                    'price_usd' => $lockedPackage->price_usd,
                    'denomination' => $lockedPackage->denomination ?: 'USD',
                    'tour_title' => $lockedPackage->tour_title,
                    'tour_details' => $lockedPackage->tour_details,
                    'duration' => $lockedPackage->duration,
                    'benefits' => $lockedPackage->benefits,
                    'description' => $lockedPackage->description,
                    'full_description' => $lockedPackage->full_description,
                    'destinations' => $lockedPackage->destinations,
                    'inclusions' => $lockedPackage->inclusions,
                    'exclusions' => $lockedPackage->exclusions,
                    'currency_label' => $this->currencyFor($method, $lockedPackage),
                    'network' => $network,
                    'payment_reference' => $reference,
                    'payment_method' => $method,
                    'status' => PackagePurchase::PENDING,
                    'submitted_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                $existing = PackagePurchase::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing) {
                    return $this->completeExistingCustomerPurchase($existing, $member, $package, $admin, $method, $reference);
                }

                throw ValidationException::withMessages([
                    'payment_reference' => 'This payment reference has already been recorded.',
                ]);
            }

            return $this->completeCustomerPurchase($purchase, $admin);
        });
    }

    /**
     * @return array{active: ?PackagePurchase, pending: ?PackagePurchase, rejected: ?PackagePurchase}
     */
    public function customerPackageState(Member $member, ?Package $package): array
    {
        if ($package === null) {
            return ['active' => null, 'pending' => null, 'rejected' => null];
        }

        $forPackage = PackagePurchase::query()
            ->where('member_id', $member->member_id)
            ->where('package_id', $package->id);

        return [
            'active' => (clone $forPackage)->where('status', PackagePurchase::APPROVED)->latest('reviewed_at')->first(),
            'pending' => (clone $forPackage)->where('status', PackagePurchase::PENDING)->latest('submitted_at')->first(),
            'rejected' => (clone $forPackage)->where('status', PackagePurchase::REJECTED)->latest('reviewed_at')->first(),
        ];
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

    private function packageDetails(PackagePurchase $purchase): ?string
    {
        $lines = array_values(array_filter([
            $purchase->tour_title,
            filled($purchase->destinations) ? 'Destinations: '.$purchase->destinations : null,
            filled($purchase->duration) ? 'Duration: '.$purchase->duration : null,
            $purchase->benefits,
        ], fn ($line) => trim((string) $line) !== ''));

        return $lines === [] ? null : implode("\n", $lines);
    }

    private function completeExistingCustomerPurchase(PackagePurchase $existing, Member $member, Package $package, User $admin, string $method, string $reference): Invoice
    {
        if ($existing->member_id !== $member->member_id
            || (int) $existing->package_id !== (int) $package->id
            || $existing->payment_method !== $method
            || $existing->payment_reference !== $reference) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'This submission was already used for a different purchase.',
            ]);
        }

        if ($existing->status === PackagePurchase::REJECTED) {
            throw ValidationException::withMessages([
                'payment_reference' => 'This payment reference was already rejected.',
            ]);
        }

        return $this->completeCustomerPurchase($existing, $admin);
    }

    private function completeCustomerPurchase(PackagePurchase $purchase, User $admin): Invoice
    {
        $hadInvoice = Invoice::query()->where('package_purchase_id', $purchase->id)->exists();
        $invoice = $this->approve($purchase, $admin);
        if ($hadInvoice) {
            return $invoice;
        }

        AdminActivityLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'customer_package_purchase',
            'subject' => $invoice->purchase_reference,
            'format' => null,
            'scope' => 'admin',
            'filters' => [
                'purchase_reference' => $invoice->purchase_reference,
                'invoice_number' => $invoice->invoice_number,
                'member_id' => $invoice->member_id,
                'package_code' => $invoice->package_code,
                'payment_method' => $invoice->payment_method,
                'payment_reference' => $invoice->payment_reference,
                'amount' => (string) $invoice->price_usd,
                'denomination' => $invoice->denomination,
            ],
            'row_count' => 1,
        ]);

        return $invoice;
    }

    private function networkFor(string $method): string
    {
        return match ($method) {
            PackagePurchase::METHOD_CASH => 'Cash',
            PackagePurchase::METHOD_BANK => 'Bank Transfer',
            default => $this->usdtNetwork(),
        };
    }

    private function usdtNetwork(): string
    {
        $network = trim((string) PaymentSetting::current()->network);
        if ($network === '' || PaymentSetting::isPlaceholder($network)) {
            return 'USDT';
        }

        return $network;
    }

    private function currencyFor(string $method, Package $package): string
    {
        if ($method === PackagePurchase::METHOD_USDT) {
            return 'USDT';
        }

        $denomination = trim((string) ($package->denomination ?: 'USD'));

        return $denomination !== '' ? $denomination : 'USD';
    }

    private function assertAdminReference(string $reference): void
    {
        $this->assertReferenceShape($reference);

        if (mb_strlen($reference) < 4 || PaymentSetting::containsSecret($reference)) {
            throw ValidationException::withMessages([
                'payment_reference' => 'Enter the receipt, transfer, or transaction reference.',
            ]);
        }

        $normalized = strtolower($reference);
        if (in_array($normalized, ['n/a', 'na', 'none', 'null', 'test', 'cash', 'pending', 'tbd', 'xxxx', '0000'], true)) {
            throw ValidationException::withMessages([
                'payment_reference' => 'Enter the reference from the cash receipt, bank transfer, or USDT transaction.',
            ]);
        }
    }

    private function assertReferenceShape(string $reference): void
    {
        if ($reference === '') {
            throw ValidationException::withMessages([
                'payment_reference' => 'Enter a transaction reference.',
            ]);
        }

        if (mb_strlen($reference) > 255) {
            throw ValidationException::withMessages([
                'payment_reference' => 'The transaction reference must be 255 characters or fewer.',
            ]);
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $reference) === 1 || str_contains($reference, '<') || str_contains($reference, '>')) {
            throw ValidationException::withMessages([
                'payment_reference' => 'The transaction reference contains characters that cannot be stored.',
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
