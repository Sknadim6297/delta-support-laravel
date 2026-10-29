<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Inserts the fixed 20-member demonstration tree. Existing rows are never updated or deleted.
 */
class DemoMemberSetup
{
    public const CONFIRM = 'setup-demo-members';

    /** @var array<string, string> */
    public const IDS = [
        'Nadim' => 'ST100004',
        'Rahul' => 'ST100005',
        'Ram' => 'ST100006',
        'Padma' => 'ST100007',
        'Suman' => 'ST100008',
        'Arjun' => 'ST100009',
        'Neha' => 'ST100010',
        'Vikram' => 'ST100011',
        'Priya' => 'ST100012',
        'Riya' => 'ST100013',
        'Amit' => 'ST100014',
        'Rohit' => 'ST100015',
        'Puja' => 'ST100016',
        'Sneha' => 'ST100017',
        'Raj' => 'ST100018',
        'Anjali' => 'ST100019',
        'Kiran' => 'ST100020',
        'Sanjay' => 'ST100021',
        'Deepa' => 'ST100022',
        'Rakesh' => 'ST100023',
    ];

    public function __construct(
        private readonly SponsorChain $sponsors,
        private readonly LocalTestMarker $marker,
        private readonly ReferralLevelEligibility $eligibility,
    ) {
    }

    /**
     * @return list<array{name: string, member_id: string, email: string, mobile: string, sponsor_id: string, sponsor_name: string, status: string, package: ?string}>
     */
    public function plans(): array
    {
        $plans = [];
        $mobile = 9188000101;
        foreach (DevelopmentMemberRoster::PARENTS as $name => $parent) {
            $plans[] = [
                'name' => $name,
                'member_id' => self::IDS[$name],
                'email' => Str::lower($name).LocalTestMarker::EMAIL_DOMAIN,
                'mobile' => (string) $mobile,
                'sponsor_id' => $parent === null ? 'ST666666' : self::IDS[$parent],
                'sponsor_name' => $parent === null ? 'Admin' : $parent,
                'status' => isset(DevelopmentMemberRoster::PACKAGES[$name]) ? 'active' : 'inactive',
                'package' => DevelopmentMemberRoster::PACKAGES[$name] ?? null,
            ];
            $mobile++;
        }

        return $plans;
    }

    /**
     * @return list<array<string, int|string>>
     */
    public function tree(): array
    {
        $rows = [];
        foreach ($this->plans() as $plan) {
            $member = Member::query()->where('member_id', $plan['member_id'])->first();
            if (! $member || ! $this->sameMember($member, $plan)) {
                continue;
            }
            $summary = $this->eligibility->forMember($member->member_id);
            $rows[] = [
                'name' => $plan['name'],
                'member_id' => $member->member_id,
                'sponsor_id' => (string) $member->sponsor_id,
                'sponsor_name' => (string) $member->sponsor_name,
                'status' => (string) $member->status,
                'package' => (string) ($plan['package'] ?? ''),
                'qualifying_directs' => $summary['qualifying_direct_count'],
                'unlocked_levels' => $summary['unlocked_levels'],
                'roi_wallet' => (string) $member->roi_wallet_amount,
                'working_wallet' => (string) $member->working_wallet_amount,
            ];
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    public function purchaseIds(): array
    {
        $ids = array_values(self::IDS);

        return PackagePurchase::query()
            ->whereIn('member_id', $ids)
            ->where('payment_reference', 'like', LocalTestMarker::PAYMENT_PREFIX.'%')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return array{conflicts: list<string>, missing_members: int, missing_purchases: int, complete: bool}
     */
    public function inspect(): array
    {
        $conflicts = [];
        if (count(self::IDS) !== 20 || count(array_unique(self::IDS)) !== 20) {
            $conflicts[] = 'The demonstration roster is not exactly 20 unique member IDs.';
        }
        if (array_keys(self::IDS) !== array_keys(DevelopmentMemberRoster::PARENTS)) {
            $conflicts[] = 'The demonstration member IDs do not match the sponsor tree.';
        }
        if ($this->sponsors->rootId() !== 'ST666666') {
            $conflicts[] = 'The configured admin sponsor is not ST666666.';
        }

        $plans = $this->plans();
        $ids = array_column($plans, 'member_id');
        $emails = array_column($plans, 'email');
        $mobiles = array_column($plans, 'mobile');
        $byId = Member::query()->whereIn('member_id', $ids)->get()->keyBy('member_id');
        $byEmail = Member::query()->whereIn('email', $emails)->get()->keyBy(fn (Member $member): string => strtolower((string) $member->email));
        $byMobile = Member::query()->whereIn('mobile_no', $mobiles)->get()->groupBy('mobile_no');

        foreach ($plans as $plan) {
            $existing = $byId->get($plan['member_id']);
            if ($existing instanceof Member && ! $this->sameMember($existing, $plan)) {
                $conflicts[] = $plan['member_id'].' already belongs to another member record.';
            }
            $emailOwner = $byEmail->get($plan['email']);
            if ($emailOwner instanceof Member && $emailOwner->member_id !== $plan['member_id']) {
                $conflicts[] = $plan['email'].' is already used by '.$emailOwner->member_id.'.';
            }
            foreach ($byMobile->get($plan['mobile'], []) as $owner) {
                if ($owner->member_id !== $plan['member_id']) {
                    $conflicts[] = $plan['mobile'].' is already used by '.$owner->member_id.'.';
                }
            }
        }

        $outside = Member::query()
            ->whereIn('sponsor_id', $ids)
            ->whereNotIn('member_id', $ids)
            ->get(['member_id', 'sponsor_id']);
        foreach ($outside as $member) {
            $conflicts[] = $member->member_id.' is already sponsored by '.$member->sponsor_id.'.';
        }

        $missingMembers = 0;
        $missingPurchases = 0;
        foreach ($plans as $plan) {
            if (! $byId->has($plan['member_id'])) {
                $missingMembers++;
            }
            if ($plan['package'] === null) {
                continue;
            }
            $reference = $this->reference($plan['member_id'], $plan['package']);
            $purchase = PackagePurchase::query()->where('payment_reference', $reference)->first();
            if (! $purchase) {
                $missingPurchases++;
                continue;
            }
            if ($purchase->member_id !== $plan['member_id'] || $purchase->status !== PackagePurchase::APPROVED) {
                $conflicts[] = $reference.' already exists and does not match the demonstration purchase.';
            }
        }

        $packages = array_values(array_unique(array_filter(array_column($plans, 'package'))));
        $active = Package::query()->whereIn('code', $packages)->where('is_active', true)->pluck('code')->all();
        foreach ($packages as $code) {
            if (! in_array($code, $active, true)) {
                $conflicts[] = $code.' is not an active package.';
            }
        }
        if (! User::query()->where('is_admin', true)->exists()) {
            $conflicts[] = 'No admin user is available.';
        }

        return [
            'conflicts' => array_values(array_unique($conflicts)),
            'missing_members' => $missingMembers,
            'missing_purchases' => $missingPurchases,
            'complete' => $conflicts === [] && $missingMembers === 0 && $missingPurchases === 0,
        ];
    }

    /**
     * @return array{created_members: int, created_purchases: int}
     */
    public function setup(): array
    {
        $inspection = $this->inspect();
        if ($inspection['conflicts'] !== []) {
            throw new RuntimeException($this->conflictMessage($inspection['conflicts']));
        }
        if ($inspection['complete']) {
            return ['created_members' => 0, 'created_purchases' => 0];
        }

        return DB::transaction(function (): array {
            $plans = $this->plans();
            $ids = array_column($plans, 'member_id');
            Member::query()
                ->where(function ($query) use ($plans, $ids): void {
                    $query->whereIn('member_id', $ids)
                        ->orWhereIn('email', array_column($plans, 'email'))
                        ->orWhereIn('mobile_no', array_column($plans, 'mobile'));
                })
                ->lockForUpdate()
                ->get();

            $inspection = $this->inspect();
            if ($inspection['conflicts'] !== []) {
                throw new RuntimeException($this->conflictMessage($inspection['conflicts']));
            }

            $preservedMembers = $this->preservedMembers($ids);
            $preservedPurchases = PackagePurchase::query()->whereNotIn('member_id', $ids)->count();
            $preservedLedger = WalletLedgerEntry::query()->count();
            $adminId = (int) User::query()->where('is_admin', true)->value('id');
            $createdMembers = 0;
            $createdPurchases = 0;
            $reviewedAt = CarbonImmutable::now('Asia/Kolkata')->startOfDay()->subDays(12);

            foreach ($plans as $plan) {
                $member = Member::query()->where('member_id', $plan['member_id'])->first();
                if (! $member) {
                    $member = Member::query()->create([
                        'member_id' => $plan['member_id'],
                        'sponsor_id' => $plan['sponsor_id'],
                        'sponsor_name' => $plan['sponsor_name'],
                        'member_name' => $plan['name'],
                        'email' => $plan['email'],
                        'mobile_no' => $plan['mobile'],
                        'password' => Hash::make(Str::password(32)),
                        'status' => $plan['status'],
                        'activation_wallet_amount' => '0.0000',
                        'working_wallet_amount' => '0.0000',
                        'roi_wallet_amount' => '0.0000',
                    ]);
                    $createdMembers++;
                }
                if ($plan['package'] === null) {
                    continue;
                }
                $reference = $this->reference($member->member_id, $plan['package']);
                if (PackagePurchase::query()->where('payment_reference', $reference)->exists()) {
                    continue;
                }
                $package = Package::query()->where('code', $plan['package'])->where('is_active', true)->firstOrFail();
                PackagePurchase::query()->create([
                    'reference' => 'DEMO-'.$member->member_id.'-'.$package->code,
                    'idempotency_key' => (string) Str::uuid(),
                    'member_id' => $member->member_id,
                    'package_id' => $package->id,
                    'package_code' => $package->code,
                    'package_name' => $package->name,
                    'package_type' => $package->package_type,
                    'price_usd' => $package->price_usd,
                    'denomination' => $package->denomination ?: 'USDT',
                    'currency_label' => 'USDT',
                    'network' => 'DEMO',
                    'payment_reference' => $reference,
                    'status' => PackagePurchase::APPROVED,
                    'submitted_at' => $reviewedAt,
                    'reviewed_at' => $reviewedAt,
                    'reviewed_by' => $adminId,
                ]);
                $createdPurchases++;
            }

            if ($this->preservedMembers($ids) !== $preservedMembers || PackagePurchase::query()->whereNotIn('member_id', $ids)->count() !== $preservedPurchases) {
                throw new RuntimeException('An existing member or purchase changed. The demonstration setup was rolled back.');
            }
            if (WalletLedgerEntry::query()->count() !== $preservedLedger) {
                throw new RuntimeException('A wallet ledger row was created. The demonstration setup was rolled back.');
            }
            foreach (Member::query()->whereIn('member_id', $ids)->get() as $member) {
                if (! $this->marker->isEmail($member->email) || ! $this->zeroWallets($member)) {
                    throw new RuntimeException($member->member_id.' is not a zero-balance demonstration member. The demonstration setup was rolled back.');
                }
            }

            return ['created_members' => $createdMembers, 'created_purchases' => $createdPurchases];
        });
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function sameMember(Member $member, array $plan): bool
    {
        return $member->member_name === $plan['name']
            && strtolower((string) $member->email) === $plan['email']
            && (string) $member->mobile_no === $plan['mobile']
            && (string) $member->sponsor_id === $plan['sponsor_id']
            && (string) $member->sponsor_name === $plan['sponsor_name']
            && (string) $member->status === $plan['status']
            && $this->marker->isEmail($member->email)
            && $this->zeroWallets($member);
    }

    private function zeroWallets(Member $member): bool
    {
        return bccomp((string) $member->roi_wallet_amount, '0', 4) === 0
            && bccomp((string) $member->working_wallet_amount, '0', 4) === 0
            && bccomp((string) $member->activation_wallet_amount, '0', 4) === 0;
    }

    private function reference(string $memberId, string $code): string
    {
        return LocalTestMarker::PAYMENT_PREFIX.$memberId.'-'.$code;
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function preservedMembers(array $ids): array
    {
        return Member::query()
            ->whereNotIn('member_id', $ids)
            ->orderBy('member_id')
            ->get(['member_id', 'sponsor_id', 'sponsor_name', 'member_name', 'email', 'mobile_no', 'status', 'roi_wallet_amount', 'working_wallet_amount', 'activation_wallet_amount'])
            ->map(fn (Member $member): string => implode('|', [
                $member->member_id,
                $member->sponsor_id,
                $member->sponsor_name,
                $member->member_name,
                $member->email,
                $member->mobile_no,
                $member->status,
                $member->roi_wallet_amount,
                $member->working_wallet_amount,
                $member->activation_wallet_amount,
            ]))
            ->all();
    }

    /**
     * @param  list<string>  $conflicts
     */
    private function conflictMessage(array $conflicts): string
    {
        return 'Demonstration setup aborted. '.implode(' ', $conflicts).' No member, purchase, or wallet was changed.';
    }
}
