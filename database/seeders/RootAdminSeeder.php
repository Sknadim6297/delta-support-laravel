<?php

namespace Database\Seeders;

use App\Services\RootAdminSeed;
use Illuminate\Database\Seeder;

class RootAdminSeeder extends Seeder
{
    public function run(RootAdminSeed $seed): void
    {
        $seed->run();

        $this->command?->info('Super Admin created. Login ID ST666666. The password is stored only as a hash.');
    }
}
