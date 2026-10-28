<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\PaymentSetting;
use App\Models\User;
use RuntimeException;

class LocalTestRoster
{
    public const PASSWORD = 'Local-Test-Only-2026';

    /** @var array<int, string> */
    private const UPLINES = [
        1 => 'Rahul',
        2 => 'Amit',
        3 => 'Priya',
        4 => 'Rohan',
        5 => 'Suman',
        6 => 'Arjun',
        7 => 'Neha',
        8 => 'Vikram',
        9 => 'Anjali',
        10 => 'Karan',
        11 => 'Meera',
        12 => 'Dev',
        13 => 'Pooja',
        14 => 'Nikhil',
        15 => 'Kavya',
        16 => 'Harsh',
        17 => 'Isha',
        18 => 'Varun',
        19 => 'Naina',
        20 => 'Tarun',
    ];

    /** @var array<int, string> */
    private const WORDS = [1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four'];

    private int $mobile = 9100000001;

    public function __construct(
        private readonly MemberRegistrationService $registration,
        private readonly PackagePurchaseService $purchases,
        private readonly LocalTestMarker $marker,
        private readonly PackageIncomePolicy $policy,
        private readonly LocalDatabaseGuard $guard,
    ) {
    }

    /**
     * @return array{members: int, purchases: int, first_date: string, ram: string, sam: string, rahul: string}
     */
    public function seed(): array
    {
        $this->guard->assertOriginalLocal();
        $this->assertReady();
        $before = $this->preservedMembers();

        $sponsorId = 'ST666666';
        $uplines = [];
        for ($level = 20; $level >= 1; $level--) {
            $uplines[$level] = $this->ensureMember(self::UPLINES[$level], $sponsorId, strtolower(self::UPLINES[$level]));
            $sponsorId = $uplines[$level]->member_id;
        }
        $ram = $this->ensureMember('Ram', $uplines[1]->member_id, 'ram');
        $sam = $this->ensureMember('Sam', $uplines[1]->member_id, 'sam');
        $this->ensurePurchase($ram, 'DEMO-HILLS');
        $this->ensurePurchase($sam, 'DEMO-COAST');
        foreach ($uplines as $level => $upline) {
            $this->ensurePurchase($upline, 'DEMO-HILLS');
            $extras = match (true) {
                $level <= 4 => 0,
                $level <= 8 => 1,
                $level <= 12 => 2,
                $level <= 16 => 3,
                default => 4,
            };
            for ($extra = 1; $extra <= $extras; $extra++) {
                $direct = $this->ensureMember(
                    'Direct '.$upline->member_name.' '.self::WORDS[$extra],
                    $upline->member_id,
                    'direct-'.$level.'-'.$extra
                );
                $this->ensurePurchase($direct, 'DEMO-HILLS');
            }
        }

        $this->assertPreserved($before);
        $purchase = PackagePurchase::query()->where('member_id', $ram->member_id)->where('package_code', 'DEMO-HILLS')->firstOrFail();
        $first = $this->policy->firstAccrualDate($purchase);

        return [
            'members' => count($this->marker->memberIds()),
            'purchases' => count($this->marker->purchaseIds()),
            'first_date' => $first?->toDateString() ?? '',
            'ram' => $ram->member_id,
            'sam' => $sam->member_id,
            'rahul' => $uplines[1]->member_id,
        ];
    }

    private function assertReady(): void
    {
        if (! PaymentSetting::current()->isReady()) {
            throw new RuntimeException('Payment settings are not ready. No test member was created.');
        }
        if (! User::query()->where('is_admin', true)->exists()) {
            throw new RuntimeException('No admin user is available to approve a test payment. No test member was created.');
        }
        $packages = Package::query()->whereIn('code', ['DEMO-HILLS', 'DEMO-COAST'])->where('is_active', true)->count();
        if ($packages !== 2) {
            throw new RuntimeException('Coastal Weekend or Hill Station Retreat is not an active package. No test member was created.');
        }
    }

    private function ensureMember(string $name, string $sponsorId, string $emailLocal): Member
    {
        $email = $emailLocal.LocalTestMarker::EMAIL_DOMAIN;
        $existing = Member::query()->where('email', $email)->first();
        if ($existing) {
            if (! $this->marker->isEmail($existing->email) || $existing->sponsor_id !== $sponsorId) {
                throw new RuntimeException('Local test member '.$existing->member_id.' already has another sponsor. No sponsor was changed.');
            }

            return $existing;
        }

        $created = $this->registration->register([
            'member_name' => $name,
            'email' => $email,
            'mobile_no' => $this->nextMobile(),
            'sponsor_id' => $sponsorId,
            'password' => self::PASSWORD,
        ], [
            'allow_admin_sponsor' => $sponsorId === 'ST666666',
        ]);

        return $created['member'];
    }

    private function ensurePurchase(Member $member, string $code): PackagePurchase
    {
        $package = Package::query()->where('code', $code)->firstOrFail();
        $reference = LocalTestMarker::PAYMENT_PREFIX.$member->member_id.'-'.$code;
        $existing = PackagePurchase::query()->where('payment_reference', $reference)->first();
        $admin = User::query()->where('is_admin', true)->firstOrFail();
        $purchase = $existing ?: $this->purchases->submit($member, $package, $reference, (string) \Illuminate\Support\Str::uuid());
        $this->purchases->approve($purchase, $admin);

        return $purchase->fresh();
    }

    private function nextMobile(): string
    {
        do {
            $mobile = (string) $this->mobile++;
        } while (Member::query()->where('mobile_no', $mobile)->count() >= 3);

        return $mobile;
    }

    /**
     * @return array<string, string>
     */
    private function preservedMembers(): array
    {
        return Member::query()
            ->where(function ($query): void {
                $query->whereNull('email')->orWhere('email', 'not like', '%'.LocalTestMarker::EMAIL_DOMAIN);
            })
            ->pluck('sponsor_id', 'member_id')
            ->all();
    }

    /**
     * @param  array<string, string>  $before
     */
    private function assertPreserved(array $before): void
    {
        $after = $this->preservedMembers();
        if ($after !== $before) {
            throw new RuntimeException('Existing member sponsor records changed. Review the local test members before using this database.');
        }
    }
}
