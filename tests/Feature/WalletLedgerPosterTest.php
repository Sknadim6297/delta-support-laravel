<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\WalletLedgerEntry;
use App\Services\WalletLedgerPoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class WalletLedgerPosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_is_refused_while_disabled_and_balances_stay_unchanged(): void
    {
        $member = $this->member('STLED001');
        $this->assertFalse(config('deltasupport.wallet_ledger.enabled'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wallet ledger posting is disabled. No balance was changed.');
        try {
            app(WalletLedgerPoster::class)->credit($member->member_id, 'roi', '2.5000', 'REF-1', (string) Str::uuid(), 'test');
        } finally {
            $this->assertSame(0, WalletLedgerEntry::query()->count());
            $this->assertSame('0.0000', $member->fresh()->roi_wallet_amount);
        }
    }

    public function test_credit_and_debit_are_idempotent_and_cannot_overdraw_or_be_edited(): void
    {
        config(['deltasupport.wallet_ledger.enabled' => true]);
        $member = $this->member('STLED002');
        $poster = app(WalletLedgerPoster::class);
        $creditKey = (string) Str::uuid();

        $poster->credit($member->member_id, 'roi', '2.5000', 'ROI-1', $creditKey, 'test credit');
        $poster->credit($member->member_id, 'roi', '2.5000', 'ROI-1', $creditKey, 'test credit');
        $poster->debit($member->member_id, 'roi', '1.0000', 'ROI-OUT-1', (string) Str::uuid(), 'test debit');

        $this->assertSame('1.5000', $member->fresh()->roi_wallet_amount);
        $this->assertSame(2, WalletLedgerEntry::query()->count());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The debit would take the wallet below 0.0000. No row was stored.');
        try {
            $poster->debit($member->member_id, 'roi', '9.0000', 'ROI-OUT-2', (string) Str::uuid(), 'too large');
        } finally {
            $this->assertSame('1.5000', $member->fresh()->roi_wallet_amount);
            $this->assertSame(2, WalletLedgerEntry::query()->count());
        }
    }

    public function test_a_stored_ledger_row_cannot_be_changed_or_removed(): void
    {
        config(['deltasupport.wallet_ledger.enabled' => true]);
        $member = $this->member('STLED003');
        $entry = app(WalletLedgerPoster::class)->credit($member->member_id, 'working', '0.6000', 'LVL-1', (string) Str::uuid(), 'test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wallet ledger entries are append-only.');
        $entry->amount = '1.0000';
        $entry->save();
    }

    private function member(string $memberId): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Sponsor',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => str_pad((string) random_int(1000000000, 1999999999), 10, '0', STR_PAD_LEFT),
            'email' => strtolower($memberId).'@example.test',
            'status' => 'active',
            'roi_wallet_amount' => '0.0000',
            'working_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }
}
