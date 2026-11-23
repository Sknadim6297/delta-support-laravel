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
use App\Models\PackagePurchaseReservation;
use App\Models\WalletLedgerEntry;
use App\Models\User;
use App\Services\PackagePurchaseFunds;
use App\Services\WalletLedgerPoster;
use App\Support\WalletLedgerDirection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PackagePurchaseService
{
    public function submitWallet(Member $member, Package $package, string $idempotencyKey): PackagePurchase
    {
        $key = trim($idempotencyKey);
        if (! \Illuminate\Support\Str::isUuid($key)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Submit the purchase once with a valid idempotency key.',
            ]);
        }

        $existing = PackagePurchase::query()->where('idempotency_key', $key)->lockForUpdate()->first();
        if ($existing) {
            if ($existing->member_id !== $member->member_id
                || (int) $existing->package_id !== (int) $package->id
                || $existing->funding_source !== PackagePurchase::FUNDING_WORKING_WALLET
                || ! in_array((string) $existing->funding_wallet, ['working', 'roi', 'combined'], true)) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'This submission was already used for a different purchase.',
                ]);
            }

            return $existing;
        }

        if (! $package->is_active) {
            throw ValidationException::withMessages([
                'package' => 'This package is not available.',
            ]);
        }
        if (strcasecmp((string) ($package->denomination ?: 'USD'), 'USDT') !== 0) {
            throw ValidationException::withMessages([
                'package' => 'Only USDT-denominated packages can be purchased with wallet funds.',
            ]);
        }

        return DB::transaction(function () use ($member, $package, $key): PackagePurchase {
            $lockedMember = Member::query()->where('member_id', $member->member_id)->lockForUpdate()->firstOrFail();
            $lockedPackage = Package::query()->whereKey($package->id)->lockForUpdate()->firstOrFail();
            if (! $lockedPackage->is_active) {
                throw ValidationException::withMessages([
                    'package' => 'This package is not available.',
                ]);
            }
            if (strcasecmp((string) ($lockedPackage->denomination ?: 'USD'), 'USDT') !== 0) {
                throw ValidationException::withMessages([
                    'package' => 'Only USDT-denominated packages can be purchased with wallet funds.',
                ]);
            }

            $existing = PackagePurchase::query()->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->member_id !== $lockedMember->member_id
                    || (int) $existing->package_id !== (int) $lockedPackage->id
                    || $existing->funding_source !== PackagePurchase::FUNDING_WORKING_WALLET) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'This submission was already used for a different purchase.',
                    ]);
                }

                return $existing;
            }

            $amount = bcadd((string) $lockedPackage->price_usd, '0', 4);
            if (bccomp($amount, '0', 4) !== 1) {
                throw ValidationException::withMessages([
                    'package' => 'This package has no valid USDT price.',
                ]);
            }

            $funds = app(PackagePurchaseFunds::class);
            $available = $funds->totalAvailable($lockedMember);
            if (bccomp($available, $amount, 4) === -1) {
                throw ValidationException::withMessages([
                    'wallet' => 'Insufficient available Working + ROI wallet balance. Available '.$available.' USDT.',
                ]);
            }

            try {
                $allocation = $funds->allocate($lockedMember, $amount);
            } catch (\RuntimeException $exception) {
                throw ValidationException::withMessages([
                    'wallet' => 'Insufficient available Working + ROI wallet balance. Available '.$available.' USDT.',
                ]);
            }
            $fundingWallet = $funds->fundingWalletLabel($allocation);

            $reservationReference = $this->uniqueReservationCode();
            $purchase = PackagePurchase::query()->create([
                'reference' => $this->uniqueCode('PUR'),
                'idempotency_key' => $key,
                'member_id' => $lockedMember->member_id,
                'package_id' => $lockedPackage->id,
                'package_code' => $lockedPackage->code,
                'package_name' => $lockedPackage->name,
                'package_type' => $lockedPackage->package_type ?: Package::TYPE_TOUR,
                'price_usd' => $amount,
                'denomination' => 'USDT',
                'tour_title' => $lockedPackage->tour_title,
                'tour_details' => $lockedPackage->tour_details,
                'duration' => $lockedPackage->duration,
                'benefits' => $lockedPackage->benefits,
                'description' => $lockedPackage->description,
                'full_description' => $lockedPackage->full_description,
                'destinations' => $lockedPackage->destinations,
                'inclusions' => $lockedPackage->inclusions,
                'exclusions' => $lockedPackage->exclusions,
                'currency_label' => 'USDT',
                'network' => 'Member Wallets',
                'payment_reference' => $reservationReference,
                'payment_method' => PackagePurchase::METHOD_WORKING_WALLET,
                'funding_source' => PackagePurchase::FUNDING_WORKING_WALLET,
                'funding_wallet' => $fundingWallet,
                'status' => PackagePurchase::PENDING,
                'submitted_at' => now(),
            ]);

            PackagePurchaseReservation::query()->create([
                'package_purchase_id' => $purchase->id,
                'member_id' => $lockedMember->member_id,
                'wallet' => $fundingWallet === 'roi' ? 'roi' : 'working',
                'reserved_amount' => $amount,
                'allocations' => $allocation,
                'status' => PackagePurchaseReservation::ACTIVE,
                'reservation_reference' => $reservationReference,
                'idempotency_key' => $key,
                'reserved_at' => now(),
            ]);

            return $purchase;
        });
    }

    public function submit(Member $member, Package $package, string $paymentReference, string $idempotencyKey): PackagePurchase
    {
        $existing = PackagePurchase::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            abort_unless($existing->member_id === $member->member_id, 403);
            abort_unless(
                (int) $existing->package_id === (int) $package->id
                && $existing->funding_source !== PackagePurchase::FUNDING_WORKING_WALLET
                && $existing->payment_reference === trim($paymentReference),
                403
            );

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
            $this->assertAdmin($admin);
            Member::query()->where('member_id', $purchase->member_id)->lockForUpdate()->firstOrFail();
            $locked = PackagePurchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($locked->funding_source === PackagePurchase::FUNDING_WORKING_WALLET) {
                $reservation = PackagePurchaseReservation::query()
                    ->where('package_purchase_id', $locked->id)
                    ->lockForUpdate()
                    ->first();
                $this->validateWalletPurchaseReplayOrPending($locked, $reservation);
            }

            $existing = Invoice::query()->where('package_purchase_id', $locked->id)->lockForUpdate()->first();
            if ($existing) {
                if ($locked->funding_source === PackagePurchase::FUNDING_WORKING_WALLET) {
                    $this->validateSettledWalletPurchase($locked);
                    if ($locked->status !== PackagePurchase::APPROVED) {
                        throw ValidationException::withMessages([
                            'purchase' => 'The settled wallet reservation conflicts with the purchase status.',
                        ]);
                    }
                }
                // Idempotent: never duplicates income-term rows or credits wallets.
                app(PackageIncomeTermRecorder::class)->record($locked->fresh());

                return $existing;
            }

            if ($locked->status !== PackagePurchase::PENDING) {
                throw ValidationException::withMessages([
                    'purchase' => 'Only a pending payment can be approved.',
                ]);
            }

            if ($locked->funding_source === PackagePurchase::FUNDING_WORKING_WALLET) {
                $this->assertWalletPurchasePostingEnabled();
                $this->settleWalletReservationLocked($locked, $admin);
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

            // Optional immutable income-term snapshot. Associate ranks fill only when
            // DELTASUPPORT_ASSOCIATE_COMMISSION_SNAPSHOT=true. Never credits wallets.
            app(PackageIncomeTermRecorder::class)->record($locked->fresh());

            return $invoice;
        });
    }

    public function recordForCustomer(Member $member, Package $package, User $admin, string $method, string $reference, string $idempotencyKey): Invoice
    {
        $this->assertAdmin($admin);
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
            $this->assertAdmin($admin);
            Member::query()->where('member_id', $purchase->member_id)->lockForUpdate()->firstOrFail();
            $locked = PackagePurchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === PackagePurchase::REJECTED) {
                return $locked;
            }
            if ($locked->status !== PackagePurchase::PENDING || $locked->invoice()->exists()) {
                throw ValidationException::withMessages([
                    'purchase' => 'Only a pending payment can be rejected.',
                ]);
            }

            if ($locked->funding_source === PackagePurchase::FUNDING_WORKING_WALLET) {
                $reservation = PackagePurchaseReservation::query()
                    ->where('package_purchase_id', $locked->id)
                    ->lockForUpdate()
                    ->first();
                if (! $reservation || $reservation->status !== PackagePurchaseReservation::ACTIVE) {
                    throw ValidationException::withMessages([
                        'purchase' => 'The linked wallet reservation is not active.',
                    ]);
                }
                $reservation->forceFill([
                    'status' => PackagePurchaseReservation::RELEASED,
                    'released_at' => now(),
                ])->save();
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

    private function uniqueReservationCode(): string
    {
        do {
            $code = 'PRES-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8));
        } while (PackagePurchaseReservation::query()->where('reservation_reference', $code)->exists());

        return $code;
    }

    private function assertAdmin(User $admin): void
    {
        if (! (bool) $admin->is_admin) {
            throw new \RuntimeException('Only an authorized administrator can perform this financial action.');
        }
    }

    private function assertWalletPurchasePostingEnabled(): void
    {
        if (! (bool) config('deltasupport.wallet_package_purchase.enabled', false)) {
            throw new \RuntimeException('Wallet-funded package purchase posting is disabled.');
        }
        if (! (bool) config('deltasupport.wallet_ledger.enabled', false)) {
            throw new \RuntimeException('Wallet ledger posting is disabled.');
        }
        if (app(FinancialControls::class)->emergencyStopped()) {
            throw new \RuntimeException('Wallet-funded package purchases are paused by the financial emergency stop.');
        }
    }

    private function validateWalletPurchaseReplayOrPending(PackagePurchase $purchase, ?PackagePurchaseReservation $reservation): void
    {
        if (! $reservation) {
            throw ValidationException::withMessages([
                'purchase' => 'The linked wallet reservation was not found.',
            ]);
        }

        if ($purchase->status === PackagePurchase::APPROVED) {
            $this->validateSettledWalletPurchase($purchase);
            return;
        }
        if ($purchase->status === PackagePurchase::REJECTED) {
            if ($reservation->status !== PackagePurchaseReservation::RELEASED) {
                throw ValidationException::withMessages([
                    'purchase' => 'A rejected wallet purchase must have a released reservation.',
                ]);
            }
            throw ValidationException::withMessages([
                'purchase' => 'Only a pending payment can be approved.',
            ]);
        }
        if ($purchase->status !== PackagePurchase::PENDING
            || $reservation->status !== PackagePurchaseReservation::ACTIVE) {
            throw ValidationException::withMessages([
                'purchase' => 'The pending purchase and active reservation status must agree.',
            ]);
        }
    }

    private function validateSettledWalletPurchase(PackagePurchase $purchase): void
    {
        $reservation = PackagePurchaseReservation::query()
            ->where('package_purchase_id', $purchase->id)
            ->lockForUpdate()
            ->first();
        if (! $reservation || $reservation->status !== PackagePurchaseReservation::SETTLED) {
            throw ValidationException::withMessages([
                'purchase' => 'The wallet reservation is not settled.',
            ]);
        }
        if (bccomp((string) $reservation->reserved_amount, (string) $purchase->price_usd, 4) !== 0) {
            throw ValidationException::withMessages([
                'purchase' => 'The settled reservation amount does not match the purchase.',
            ]);
        }

        $allocations = $this->reservationAllocations($reservation);
        $debits = WalletLedgerEntry::query()
            ->where('package_purchase_id', $purchase->id)
            ->whereIn('wallet', array_keys($allocations))
            ->where('direction', WalletLedgerDirection::DEBIT)
            ->get();
        if ($debits->count() !== count($allocations)) {
            throw ValidationException::withMessages([
                'purchase' => 'The settled wallet purchase must have one debit ledger entry per funded wallet.',
            ]);
        }
        $debitTotal = '0.0000';
        foreach ($allocations as $wallet => $part) {
            $debit = $debits->firstWhere('wallet', $wallet);
            $modernRef = 'PKG-DEBIT-'.$purchase->reference.'-'.strtoupper($wallet);
            $legacyRef = 'PKG-DEBIT-'.$purchase->reference;
            $refOk = $debit !== null && (
                $debit->reference === $modernRef
                || (count($allocations) === 1 && $wallet === 'working' && $debit->reference === $legacyRef)
            );
            $key = (string) ($debit?->idempotency_key ?? '');
            $keyOk = $key === (string) $reservation->idempotency_key
                || $key === (string) $reservation->idempotency_key.'|'.$wallet;
            if ($debit === null || bccomp((string) $debit->amount, $part, 4) !== 0 || ! $refOk || ! $keyOk) {
                throw ValidationException::withMessages([
                    'purchase' => 'The wallet debit ledger entry is inconsistent.',
                ]);
            }
            $debitTotal = bcadd($debitTotal, (string) $debit->amount, 4);
        }
        if (bccomp($debitTotal, (string) $reservation->reserved_amount, 4) !== 0) {
            throw ValidationException::withMessages([
                'purchase' => 'The wallet debit total does not match the reservation.',
            ]);
        }
    }

    private function settleWalletReservationLocked(PackagePurchase $purchase, User $admin): void
    {
        if (! in_array((string) $purchase->funding_wallet, ['working', 'roi', 'combined'], true)) {
            throw ValidationException::withMessages([
                'purchase' => 'The purchase does not use a supported wallet.',
            ]);
        }

        $member = Member::query()->where('member_id', $purchase->member_id)->lockForUpdate()->firstOrFail();
        $reservation = PackagePurchaseReservation::query()
            ->where('package_purchase_id', $purchase->id)
            ->lockForUpdate()
            ->first();
        if (! $reservation) {
            throw ValidationException::withMessages([
                'purchase' => 'The linked wallet reservation was not found.',
            ]);
        }
        if ($reservation->status === PackagePurchaseReservation::SETTLED) {
            $this->validateSettledWalletPurchase($purchase);
            return;
        }
        if ($reservation->status !== PackagePurchaseReservation::ACTIVE) {
            throw ValidationException::withMessages([
                'purchase' => 'The linked wallet reservation is not active.',
            ]);
        }

        if (WalletLedgerEntry::query()
            ->where('package_purchase_id', $purchase->id)
            ->where('direction', WalletLedgerDirection::DEBIT)
            ->exists()) {
            throw ValidationException::withMessages([
                'purchase' => 'A wallet debit exists while the reservation is still active.',
            ]);
        }

        $amount = bcadd((string) $reservation->reserved_amount, '0', 4);
        if (bccomp($amount, (string) $purchase->price_usd, 4) !== 0) {
            throw ValidationException::withMessages([
                'purchase' => 'The reservation amount does not match the purchase price.',
            ]);
        }

        $allocations = $this->reservationAllocations($reservation);
        $poster = app(WalletLedgerPoster::class);
        foreach ($allocations as $wallet => $part) {
            $column = match ($wallet) {
                'working' => 'working_wallet_amount',
                'roi' => 'roi_wallet_amount',
                default => throw ValidationException::withMessages([
                    'purchase' => 'The purchase does not use a supported wallet.',
                ]),
            };
            $opening = bcadd((string) ($member->{$column} ?? '0'), '0', 4);
            if (bccomp($opening, $part, 4) === -1) {
                throw ValidationException::withMessages([
                    'purchase' => 'The '.ucfirst($wallet).' Wallet no longer has enough funds to settle this purchase.',
                ]);
            }
            $closing = bcsub($opening, $part, 4);
            $member->{$column} = $closing;
            $member->save();

            $poster->append(
                WalletLedgerDirection::DEBIT,
                $member->member_id,
                $wallet,
                $part,
                'PKG-DEBIT-'.$purchase->reference.'-'.strtoupper($wallet),
                (string) $reservation->idempotency_key.'|'.$wallet,
                'Wallet-funded package purchase '.$purchase->reference,
                $purchase->id,
                $opening,
                $closing,
                null,
                'admin:'.$admin->id
            );
        }

        $reservation->forceFill([
            'status' => PackagePurchaseReservation::SETTLED,
            'settled_at' => now(),
        ])->save();
    }

    /**
     * @return array<string, string>
     */
    private function reservationAllocations(PackagePurchaseReservation $reservation): array
    {
        if (is_array($reservation->allocations) && $reservation->allocations !== []) {
            $normalized = [];
            foreach ($reservation->allocations as $wallet => $part) {
                $amount = bcadd((string) $part, '0', 4);
                if (bccomp($amount, '0', 4) === 1) {
                    $normalized[(string) $wallet] = $amount;
                }
            }
            if ($normalized !== []) {
                return $normalized;
            }
        }

        return ['working' => bcadd((string) $reservation->reserved_amount, '0', 4)];
    }
}
