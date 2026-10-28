<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Opt-in synthetic dataset: php artisan db:seed --class=StressTestSeeder
 * Not called by DatabaseSeeder. Refuses to run if members or any financial
 * transactions already exist.
 */
class StressTestSeeder extends Seeder
{
    private const COUNT = 20000;

    private const ROOT_SPONSOR = 'ST666666';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Refused. The stress-test seeder does not run in production.');
        }

        foreach (['members', 'investments', 'roi_transactions', 'level_commission_transactions'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException("Refusing to seed: {$table} is not empty.");
            }
        }

        $this->call(RankSeeder::class);

        $count = (int) env('STRESS_MEMBER_COUNT', self::COUNT);
        $password = Hash::make('password');
        $now = now()->subDays(2)->toDateTimeString();

        // Ternary tree: member N sponsors from member floor((N - 2) / 3) + 1.
        foreach (array_chunk(range(1, $count), 500) as $numbers) {
            $members = [];
            $investments = [];
            foreach ($numbers as $i) {
                $memberId = sprintf('ST%06d', $i);
                $sponsorId = $i <= 3 ? self::ROOT_SPONSOR : sprintf('ST%06d', intdiv($i - 2, 3));
                $name = 'Stress Member ' . $i;
                $members[] = [
                    'member_id' => $memberId,
                    'sponsor_id' => $sponsorId,
                    'sponsor_name' => $i <= 3 ? 'Company' : 'Stress Member ' . intdiv($i - 2, 3),
                    'member_name' => $name,
                    'mobile_no' => (string) (9000000000 + $i),
                    'pan_card_no' => sprintf('STR%07d', $i),
                    'email' => "stress{$i}@example.test",
                    'password' => $password,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $investments[] = [
                    'investment_id' => sprintf('INV-STRESS-%06d', $i),
                    'member_id' => $memberId,
                    'member_name' => $name,
                    'amount' => '100.0000',
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('members')->insert($members);
            DB::table('investments')->insert($investments);
        }
    }
}
