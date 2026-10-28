<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('packages')
            ->where('code', 'DEMO-HILLS')
            ->where('description', 'A demo tour package. It does not credit ROI or commission.')
            ->update([
                'description' => 'A demo tour package for local testing.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
    }
};
