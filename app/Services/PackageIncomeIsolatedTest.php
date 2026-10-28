<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Reads the main database and writes only to delta_support_income_test.
 * package-income:accrue remains disabled for the main connection.
 */
class PackageIncomeIsolatedTest
{
    public const DATABASE = 'delta_support_income_test';

    public const CONFIRM = 'isolated-test';

    public function configuredName(): string
    {
        return (string) config('database.connections.income_test.database');
    }

    public function mainName(): string
    {
        return (string) config('database.connections.mysql.database');
    }

    public function assertConfigured(): void
    {
        if ($this->configuredName() !== self::DATABASE || $this->configuredName() === $this->mainName()) {
            throw new RuntimeException('Refused. package-income:test will not write to the main database.');
        }
    }

    public function assertConnected(): string
    {
        $this->assertConfigured();
        $actual = (string) DB::connection('income_test')->getDatabaseName();
        if ($actual !== self::DATABASE || $actual === $this->mainName()) {
            throw new RuntimeException('Refused. The connected test database is not '.self::DATABASE.'. No wallet was credited.');
        }

        return $actual;
    }

    public function useTestConnection(): string
    {
        $actual = $this->assertConnected();
        config(['database.default' => 'income_test']);
        DB::setDefaultConnection('income_test');
        if ((string) DB::connection()->getDatabaseName() !== self::DATABASE) {
            throw new RuntimeException('Refused. The default connection is not the isolated test database.');
        }

        return $actual;
    }

    public function prepare(): array
    {
        $database = $this->assertConnected();
        $test = DB::connection('income_test');
        $test->statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['wallet_ledger_entries', 'package_level_accruals', 'package_roi_accruals', 'package_income_runs', 'package_purchases', 'members', 'packages', 'ranks'] as $table) {
            if (Schema::connection('income_test')->hasTable($table)) {
                $test->table($table)->delete();
            }
        }
        $copied = [
            'ranks' => $this->copy('ranks'),
            'packages' => $this->copy('packages'),
            'members' => $this->copy('members', ['password' => null, 'remember_token' => null]),
            'package_purchases' => $this->copy('package_purchases', ['reviewed_by' => null]),
        ];
        $test->statement('SET FOREIGN_KEY_CHECKS=1');

        return ['database' => $database, 'copied' => $copied];
    }

    /**
     * Disposable uplines for a 20-level check. Copied member sponsor ids are not changed.
     */
    public function commissionChain(): array
    {
        $this->useTestConnection();
        if (Member::query()->where('member_id', 'STTHBUY')->exists()) {
            return ['created' => false, 'buyer' => 'STTHBUY', 'coastal' => 'STTCOAST'];
        }
        if (! Package::query()->where('code', 'DEMO-HILLS')->exists() || ! Package::query()->where('code', 'DEMO-COAST')->exists()) {
            throw new RuntimeException('The isolated database has no copied packages. Run prepare first.');
        }

        $hills = Package::query()->where('code', 'DEMO-HILLS')->firstOrFail();
        $coast = Package::query()->where('code', 'DEMO-COAST')->firstOrFail();
        $sponsorId = 'ST666666';
        $uplines = [];
        for ($level = 20; $level >= 1; $level--) {
            $id = 'STT'.str_pad((string) $level, 2, '0', STR_PAD_LEFT);
            $uplines[$level] = $this->member($id, $sponsorId);
            $sponsorId = $id;
        }
        $buyer = $this->member('STTHBUY', $uplines[1]->member_id);
        $coastal = $this->member('STTCOAST', $uplines[1]->member_id);
        $this->purchase($buyer, $hills, '1000.0000');
        $this->purchase($coastal, $coast, '100.0000');
        foreach ($uplines as $level => $upline) {
            $this->purchase($upline, $hills, '1000.0000');
            $extras = match (true) {
                $level <= 4 => 0,
                $level <= 8 => 1,
                $level <= 12 => 2,
                $level <= 16 => 3,
                default => 4,
            };
            for ($extra = 1; $extra <= $extras; $extra++) {
                $direct = $this->member('STTX'.$level.$extra, $upline->member_id);
                $this->purchase($direct, $hills, '1000.0000');
            }
        }

        return ['created' => true, 'buyer' => $buyer->member_id, 'coastal' => $coastal->member_id];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function copy(string $table, array $overrides = []): int
    {
        if (! Schema::connection('mysql')->hasTable($table) || ! Schema::connection('income_test')->hasTable($table)) {
            return 0;
        }
        $columns = array_values(array_intersect(
            Schema::connection('mysql')->getColumnListing($table),
            Schema::connection('income_test')->getColumnListing($table)
        ));
        $main = DB::connection('mysql');
        $test = DB::connection('income_test');
        $copied = 0;
        $main->table($table)->orderBy('id')->chunk(100, function ($rows) use ($test, $table, $columns, $overrides, &$copied): void {
            $payload = [];
            foreach ($rows as $row) {
                $item = [];
                foreach ($columns as $column) {
                    $item[$column] = array_key_exists($column, $overrides) ? $overrides[$column] : $row->{$column};
                }
                $payload[] = $item;
            }
            if ($payload !== []) {
                $test->table($table)->insert($payload);
                $copied += count($payload);
            }
        });

        return $copied;
    }

    private function member(string $memberId, string $sponsorId): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => $sponsorId,
            'sponsor_name' => $sponsorId === 'ST666666' ? 'Admin' : 'Test sponsor',
            'member_name' => 'Isolated '.$memberId,
            'mobile_no' => '8'.str_pad((string) abs(crc32($memberId)), 9, '0', STR_PAD_LEFT),
            'email' => strtolower($memberId).'@income-test.local',
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }

    private function purchase(Member $member, Package $package, string $price): PackagePurchase
    {
        return PackagePurchase::query()->create([
            'reference' => 'PUR-'.$member->member_id,
            'idempotency_key' => (string) Str::uuid(),
            'member_id' => $member->member_id,
            'package_id' => $package->id,
            'package_code' => $package->code,
            'package_name' => $package->name,
            'package_type' => $package->package_type,
            'price_usd' => $price,
            'denomination' => 'USDT',
            'network' => 'TRC20',
            'payment_reference' => 'TX'.$member->member_id,
            'status' => PackagePurchase::APPROVED,
            'submitted_at' => '2026-10-01 06:00:00',
            'reviewed_at' => '2026-10-01 06:00:00',
        ]);
    }
}
