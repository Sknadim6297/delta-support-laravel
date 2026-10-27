<?php

use App\Models\PaymentSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('payment_settings')) {
            return;
        }

        foreach (DB::table('payment_settings')->orderBy('id')->get() as $row) {
            $updates = [];
            $instructions = (string) ($row->instructions ?? '');
            if ($instructions !== '' && stripos($instructions, 'USD package amount') !== false) {
                $updates['instructions'] = PaymentSetting::USDT_INSTRUCTION;
            }
            if ((int) $row->is_enabled === 1 && PaymentSetting::isPlaceholder((string) $row->network)) {
                $updates['is_enabled'] = 0;
            }
            if ($updates !== []) {
                $updates['updated_at'] = now();
                DB::table('payment_settings')->where('id', $row->id)->update($updates);
            }
        }
    }

    public function down(): void
    {
        // The previous instruction text is not restored. Purchase and invoice snapshots were not changed.
    }
};
