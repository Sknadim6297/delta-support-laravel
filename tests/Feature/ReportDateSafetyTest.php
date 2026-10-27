<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportDateSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_report_dates_stay_on_the_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $filters = ['from_date' => '2026-02-31', 'to_date' => 'not-a-date'];

        $this->actingAs($admin)
            ->get(route('admin.report.roi-report', $filters))
            ->assertOk()
            ->assertSee('Total Amount (USDT)');

        $this->actingAs($admin)
            ->get(route('admin.report.level-income', $filters))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.report.rank-achievement', $filters))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.invoices.index', $filters))
            ->assertOk();
    }

    public function test_private_storage_requires_a_signature(): void
    {
        $this->get('/storage/packages/secret.png')->assertForbidden();
    }
}
