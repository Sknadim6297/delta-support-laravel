<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\PaymentSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Twenty fictional local-test members. Existing accounts outside @local-test.invalid are never changed.
 */
class DevelopmentMemberRoster
{
    /** @var array<string, string|null> */
    public const PARENTS = [
        'Nadim' => null,
        'Rahul' => 'Nadim',
        'Ram' => 'Nadim',
        'Padma' => 'Nadim',
        'Suman' => 'Rahul',
        'Arjun' => 'Rahul',
        'Neha' => 'Ram',
        'Vikram' => 'Ram',
        'Priya' => 'Padma',
        'Riya' => 'Padma',
        'Amit' => 'Suman',
        'Rohit' => 'Suman',
        'Puja' => 'Arjun',
        'Sneha' => 'Arjun',
        'Raj' => 'Neha',
        'Anjali' => 'Neha',
        'Kiran' => 'Vikram',
        'Sanjay' => 'Vikram',
        'Deepa' => 'Priya',
        'Rakesh' => 'Riya',
    ];

    /** @var array<string, string> */
    public const PACKAGES = [
        'Nadim' => 'DEMO-HILLS',
        'Rahul' => 'DEMO-HILLS',
        'Ram' => 'DEMO-HILLS',
        'Padma' => 'DEMO-HILLS',
        'Suman' => 'DEMO-COAST',
        'Neha' => 'DEMO-COAST',
    ];

    public function __construct(
        private readonly MemberRegistrationService $registration,
        private readonly PackagePurchaseService $purchases,
        private readonly LocalTestMarker $marker,
        private readonly LocalTestCleanup $cleanup,
        private readonly LocalDatabaseGuard $guard,
        private readonly ReferralLevelEligibility $eligibility,
    ) {
    }

    /**
     * @return array<string, string|null>
     */
    public static function parents(): array
    {
        return self::PARENTS;
    }

    public function emailFor(string $name): string
    {
        return Str::lower($name).LocalTestMarker::EMAIL_DOMAIN;
    }

    /**
     * @return array{members: int, created: int, purchases: int, replaced_local_test: bool, ids: array<string, string>}
     */
    public function seed(): array
    {
        $this->guard->assertOriginalLocal();
        $this->assertReady();
        $preserved = $this->preservedSponsors();
        $replaced = false;

        if (! $this->aligned()) {
            $report = $this->cleanup->inspect();
            if ($report['blocked'] !== '') {
                throw new RuntimeException((string) $report['blocked'].' No development member was created.');
            }
            if ((int) $report['members'] > 0) {
                $this->cleanup->delete();
                $replaced = true;
            }
        }

        $members = [];
        $created = 0;
        foreach (self::PARENTS as $name => $parent) {
            $sponsorId = $parent === null
                ? $this->registrationRoot()
                : $members[$parent]->member_id;
            $before = Member::query()->where('email', $this->emailFor($name))->exists();
            $members[$name] = $this->ensureMember($name, $sponsorId);
            if (! $before) {
                $created++;
            }
        }

        $purchaseCount = 0;
        foreach (self::PACKAGES as $name => $code) {
            $this->ensurePurchase($members[$name], $code);
            $purchaseCount++;
        }

        $this->assertPreserved($preserved);
        $this->assertNetwork($members);

        return [
            'members' => count($members),
            'created' => $created,
            'purchases' => $purchaseCount,
            'replaced_local_test' => $replaced,
            'ids' => array_map(fn (Member $member): string => $member->member_id, $members),
        ];
    }

    /**
     * @param  array<string, Member>  $members
     * @return list<array<string, int|string>>
     */
    public function eligibility(array $members): array
    {
        $rows = [];
        foreach ($members as $name => $member) {
            $summary = $this->eligibility->forMember($member->member_id);
            $rows[] = [
                'name' => $name,
                'member_id' => $member->member_id,
                'sponsor_id' => $member->sponsor_id,
                'sponsor_name' => $member->sponsor_name,
                'status' => $member->status,
                'package' => self::PACKAGES[$name] ?? '',
                'qualifying_directs' => $summary['qualifying_direct_count'],
                'unlocked_levels' => $summary['unlocked_levels'],
                'roi_wallet' => (string) $member->roi_wallet_amount,
                'working_wallet' => (string) $member->working_wallet_amount,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, Member>
     */
    public function currentMembers(): array
    {
        $members = [];
        foreach (array_keys(self::PARENTS) as $name) {
            $member = Member::query()->where('email', $this->emailFor($name))->first();
            if ($member) {
                $members[$name] = $member;
            }
        }

        return $members;
    }

    private function aligned(): bool
    {
        $emails = array_map($this->emailFor(...), array_keys(self::PARENTS));
        $extras = Member::query()
            ->where('email', 'like', '%'.LocalTestMarker::EMAIL_DOMAIN)
            ->whereNotIn('email', $emails)
            ->count();
        if ($extras > 0) {
            return false;
        }

        $byEmail = Member::query()->whereIn('email', $emails)->get()->keyBy(fn (Member $member): string => strtolower((string) $member->email));
        if ($byEmail->count() !== count(self::PARENTS)) {
            return false;
        }

        $ids = [];
        foreach (self::PARENTS as $name => $parent) {
            $member = $byEmail->get($this->emailFor($name));
            if (! $member instanceof Member) {
                return false;
            }
            $expectedSponsor = $parent === null ? $this->registrationRoot() : ($ids[$parent] ?? '');
            if ($member->sponsor_id !== $expectedSponsor) {
                return false;
            }
            $ids[$name] = $member->member_id;
        }

        return true;
    }

    private function ensureMember(string $name, string $sponsorId): Member
    {
        $email = $this->emailFor($name);
        $existing = Member::query()->where('email', $email)->first();
        if ($existing) {
            if (! $this->marker->isEmail($existing->email) || $existing->sponsor_id !== $sponsorId || $existing->member_name !== $name) {
                throw new RuntimeException('Local test member '.$existing->member_id.' does not match the development roster. No sponsor was changed.');
            }

            return $existing;
        }

        $created = $this->registration->register([
            'member_name' => $name,
            'email' => $email,
            'mobile_no' => $this->nextMobile(),
            'sponsor_id' => $sponsorId,
            'password' => LocalTestRoster::PASSWORD,
        ], [
            'allow_admin_sponsor' => $sponsorId === $this->registrationRoot(),
        ]);

        return $created['member'];
    }

    private function ensurePurchase(Member $member, string $code): PackagePurchase
    {
        $package = Package::query()->where('code', $code)->where('is_active', true)->first();
        if (! $package) {
            throw new RuntimeException($code.' is not an active package. No purchase was created.');
        }

        $reference = LocalTestMarker::PAYMENT_PREFIX.$member->member_id.'-'.$code;
        $existing = PackagePurchase::query()->where('payment_reference', $reference)->first();
        $admin = User::query()->where('is_admin', true)->firstOrFail();
        $purchase = $existing ?: $this->purchases->submit($member, $package, $reference, (string) Str::uuid());
        if ($purchase->status === PackagePurchase::PENDING) {
            $this->purchases->approve($purchase, $admin);
            $purchase = $purchase->fresh();
        }
        if ($purchase->status !== PackagePurchase::APPROVED) {
            throw new RuntimeException('Local test purchase '.$reference.' is not approved. No wallet was credited.');
        }
        $this->simulateWaitElapsed($purchase);

        return $purchase->fresh();
    }

    private function simulateWaitElapsed(PackagePurchase $purchase): void
    {
        if (! $this->marker->isPurchase($purchase)) {
            throw new RuntimeException('Refused to age a purchase that is not local test data.');
        }

        $target = CarbonImmutable::now('Asia/Kolkata')->startOfDay()->subDays(12);
        if ($purchase->reviewed_at !== null && CarbonImmutable::parse($purchase->reviewed_at)->lessThanOrEqualTo($target)) {
            return;
        }

        $purchase->forceFill(['reviewed_at' => $target])->save();
        Invoice::query()->where('package_purchase_id', $purchase->id)->update([
            'approved_at' => $target,
        ]);
    }

    private int $mobile = 9188000101;

    private function nextMobile(): string
    {
        do {
            $candidate = (string) $this->mobile++;
        } while (Member::query()->where('mobile_no', $candidate)->exists());

        return $candidate;
    }

    private function assertReady(): void
    {
        if (! PaymentSetting::current()->isReady()) {
            throw new RuntimeException('Payment settings are not ready. No development member was created.');
        }
        if (! User::query()->where('is_admin', true)->exists()) {
            throw new RuntimeException('No admin user is available. No development member was created.');
        }
        $packages = Package::query()->whereIn('code', array_values(array_unique(self::PACKAGES)))->where('is_active', true)->count();
        if ($packages !== count(array_unique(self::PACKAGES))) {
            throw new RuntimeException('A development package is missing. No development member was created.');
        }
    }

    private function registrationRoot(): string
    {
        return (new SponsorChain)->rootId();
    }

    /**
     * @return array<string, string|null>
     */
    private function preservedSponsors(): array
    {
        return Member::query()
            ->where(function ($query): void {
                $query->whereNull('email')->orWhere('email', 'not like', '%'.LocalTestMarker::EMAIL_DOMAIN);
            })
            ->pluck('sponsor_id', 'member_id')
            ->all();
    }

    /**
     * @param  array<string, string|null>  $before
     */
    private function assertPreserved(array $before): void
    {
        $after = $this->preservedSponsors();
        if ($after !== $before) {
            throw new RuntimeException('An existing non-local member changed. No further development member was created.');
        }
    }

    /**
     * @param  array<string, Member>  $members
     */
    private function assertNetwork(array $members): void
    {
        if (count($members) !== 20) {
            throw new RuntimeException('The development roster does not contain 20 members.');
        }

        $ids = [];
        foreach ($members as $member) {
            if (isset($ids[$member->member_id])) {
                throw new RuntimeException('A development member ID was repeated.');
            }
            $ids[$member->member_id] = true;
            if ((new SponsorChain)->isRoot($member->sponsor_id) && $member->sponsor_name !== 'Admin') {
                throw new RuntimeException($member->member_id.' does not record the admin sponsor name.');
            }
            $seen = [$member->member_id => true];
            $cursor = $member->sponsor_id;
            $ended = false;
            for ($step = 0; $step < 25; $step++) {
                if ((new SponsorChain)->isRoot($cursor)) {
                    $ended = true;
                    break;
                }
                if (isset($seen[$cursor])) {
                    throw new RuntimeException('The development roster contains a circular sponsor.');
                }
                $seen[$cursor] = true;
                $parent = Member::query()->where('member_id', $cursor)->first();
                if (! $parent) {
                    throw new RuntimeException('Sponsor '.$cursor.' has no member record.');
                }
                if ($parent->member_name !== $member->sponsor_name && $member->sponsor_id === $parent->member_id) {
                    throw new RuntimeException($member->member_id.' has a sponsor name that does not match '.$parent->member_id.'.');
                }
                $cursor = (string) $parent->sponsor_id;
            }
            if (! $ended) {
                throw new RuntimeException($member->member_id.' does not reach the admin sponsor.');
            }
        }
    }
}
