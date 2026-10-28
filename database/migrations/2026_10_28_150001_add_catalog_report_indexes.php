<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('members', 'created_at', 'members_created_at_index');
        $this->addIndex('roi_transactions', 'roi_date', 'roi_transactions_roi_date_index');
        $this->addIndex('activation_wallet_transactions', 'created_at', 'activation_wallet_created_at_index');
    }

    public function down(): void
    {
        $this->dropIndex('members', 'members_created_at_index');
        $this->dropIndex('roi_transactions', 'roi_transactions_roi_date_index');
        $this->dropIndex('activation_wallet_transactions', 'activation_wallet_created_at_index');
    }

    private function addIndex(string $table, string $column, string $name): void
    {
        $existing = array_column(Schema::getIndexes($table), 'name');
        if (in_array($name, $existing, true)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $name) {
            $blueprint->index($column, $name);
        });
    }

    private function dropIndex(string $table, string $name): void
    {
        $existing = array_column(Schema::getIndexes($table), 'name');
        if (! in_array($name, $existing, true)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name) {
            $blueprint->dropIndex($name);
        });
    }
};
