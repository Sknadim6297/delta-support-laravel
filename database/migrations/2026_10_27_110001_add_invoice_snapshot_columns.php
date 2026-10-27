<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoices', 'package_code')) {
                $table->string('package_code')->nullable()->after('package_name');
            }
            if (! Schema::hasColumn('invoices', 'purchase_reference')) {
                $table->string('purchase_reference')->nullable()->after('package_purchase_id');
            }
            if (! Schema::hasColumn('invoices', 'package_details')) {
                $table->text('package_details')->nullable()->after('package_code');
            }
            if (! $this->indexExists('invoices', 'invoices_approved_at_index')) {
                $table->index('approved_at');
            }
        });

        if (! Schema::hasColumn('invoices', 'package_code')) {
            return;
        }

        DB::table('invoices')->whereNull('package_code')->orderBy('id')->each(function (object $invoice): void {
            $purchase = DB::table('package_purchases')->where('id', $invoice->package_purchase_id)->first();
            if (! $purchase) {
                return;
            }

            $details = array_values(array_filter([
                $purchase->tour_title ?? null,
                filled($purchase->destinations ?? null) ? 'Destinations: '.$purchase->destinations : null,
                filled($purchase->duration ?? null) ? 'Duration: '.$purchase->duration : null,
                $purchase->benefits ?? null,
            ], fn ($line) => trim((string) $line) !== ''));

            DB::table('invoices')->where('id', $invoice->id)->update([
                'package_code' => $purchase->package_code,
                'purchase_reference' => $purchase->reference,
                'package_details' => $details === [] ? null : implode("\n", $details),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            if ($this->indexExists('invoices', 'invoices_approved_at_index')) {
                $table->dropIndex('invoices_approved_at_index');
            }
            $table->dropColumn(['package_code', 'purchase_reference', 'package_details']);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = $connection->select('PRAGMA index_list('.$table.')');

            return collect($indexes)->contains(fn ($row) => ($row->name ?? null) === $index);
        }

        $database = $connection->getDatabaseName();
        $found = $connection->select(
            'select 1 as present from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
            [$database, $table, $index]
        );

        return $found !== [];
    }
};
