<?php

namespace Tests\Feature;

use App\Mail\SupportTicketNoticeMail;
use App\Models\FinancialControl;
use App\Models\Member;
use App\Models\SupportMessage;
use App\Models\SupportNotification;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_open_a_ticket_and_only_the_owner_can_read_it(): void
    {
        Mail::fake();
        $member = $this->member('ST200001');
        $other = $this->member('ST200002');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.support.store'), $this->ticketPayload())
            ->assertRedirect();

        $ticket = SupportTicket::query()->firstOrFail();
        $this->assertSame('TKT000001', $ticket->reference);
        $this->assertSame(SupportTicket::OPEN, $ticket->status);
        $this->assertSame('Wallet transfer did not show', $ticket->subject);
        $this->assertSame(1, $ticket->messages()->count());
        $this->assertSame(1, SupportNotification::query()->where('audience', 'admin')->where('user_id', $admin->id)->count());
        Mail::assertSent(SupportTicketNoticeMail::class);

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.support.store'), $this->ticketPayload())
            ->assertRedirect(route('member.support.show', $ticket));
        $this->assertSame(1, SupportTicket::query()->count());

        $this->withSession(['member_context_id' => $other->member_id])
            ->get(route('member.support.show', $ticket))
            ->assertNotFound();
        $this->withSession(['member_context_id' => $other->member_id])
            ->get(route('member.support'))
            ->assertOk()
            ->assertDontSeeText('TKT000001');
        $this->flushSession();
        $this->get(route('member.support'))->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.support.index'))
            ->assertForbidden();
        $this->actingAs($admin)
            ->get(route('admin.support.index'))
            ->assertOk()
            ->assertSeeText('TKT000001');
        $this->assertFalse((bool) FinancialControl::current()->withdrawal_requests_enabled);
        $this->assertSame('0.0000', (string) $member->fresh()->working_wallet_amount);
    }

    public function test_replies_status_changes_and_closed_tickets_are_restricted(): void
    {
        Mail::fake();
        $member = $this->member('ST200010');
        $admin = User::factory()->create(['is_admin' => true]);
        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.support.store'), $this->ticketPayload('11111111-1111-4111-8111-111111111111'))
            ->assertRedirect();
        $ticket = SupportTicket::query()->firstOrFail();

        $this->actingAs($admin)->post(route('admin.support.reply', $ticket), [
            'message' => 'We are checking the transfer screen.',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('admin.support.show', $ticket));
        $this->assertSame(SupportTicket::AWAITING_MEMBER, $ticket->fresh()->status);
        $this->assertSame(1, SupportNotification::query()->where('audience', 'member')->where('member_id', $member->member_id)->count());

        $replyKey = (string) Str::uuid();
        $this->withSession(['member_context_id' => $member->member_id])->post(route('member.support.reply', $ticket), [
            'message' => 'It still shows the same balance.',
            'idempotency_key' => $replyKey,
        ])->assertRedirect();
        $this->withSession(['member_context_id' => $member->member_id])->post(route('member.support.reply', $ticket), [
            'message' => 'A second copy of the same reply.',
            'idempotency_key' => $replyKey,
        ])->assertRedirect();
        $this->assertSame(3, $ticket->messages()->count());
        $this->assertSame(SupportTicket::AWAITING_ADMIN, $ticket->fresh()->status);

        $this->actingAs($admin)->post(route('admin.support.status', $ticket), [
            'status' => SupportTicket::RESOLVED,
        ])->assertRedirect();
        $this->assertSame(SupportTicket::RESOLVED, $ticket->fresh()->status);

        $this->withSession(['member_context_id' => $member->member_id])->post(route('member.support.reply', $ticket), [
            'message' => 'Please look again.',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('message');

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.support.close', $ticket))
            ->assertRedirect();
        $this->assertSame(SupportTicket::CLOSED, $ticket->fresh()->status);
        $this->actingAs($admin)->post(route('admin.support.reply', $ticket), [
            'message' => 'Too late.',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('message');

        $this->actingAs($admin)->post(route('admin.support.status', $ticket), [
            'status' => SupportTicket::AWAITING_ADMIN,
        ])->assertRedirect();
        $this->assertSame(SupportTicket::AWAITING_ADMIN, $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->closed_at);

        $message = SupportMessage::query()->firstOrFail();
        try {
            $message->body = 'Edited';
            $message->save();
            $this->fail('A support message was edited.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Support messages cannot be edited.', $exception->getMessage());
        }
    }

    public function test_attachments_stay_private_and_invalid_files_are_rejected(): void
    {
        Mail::fake();
        Storage::fake('local');
        $member = $this->member('ST200020');
        $other = $this->member('ST200021');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.support.store'), $this->ticketPayload((string) Str::uuid(), [
                'attachment' => UploadedFile::fake()->image('proof.jpg'),
            ]))
            ->assertRedirect();

        $ticket = SupportTicket::query()->firstOrFail();
        $attachment = $ticket->messages()->firstOrFail()->attachment;
        $this->assertNotNull($attachment);
        $this->assertSame('local', $attachment->disk);
        $this->assertStringStartsWith('support-attachments/', $attachment->path);
        $this->assertStringNotContainsString('..', $attachment->path);
        Storage::disk('local')->assertExists($attachment->path);
        Storage::disk('public')->assertMissing($attachment->path);

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.support.attachments.show', $attachment))
            ->assertOk();
        $this->actingAs($admin)
            ->get(route('admin.support.attachments.show', $attachment))
            ->assertOk();
        $this->withSession(['member_context_id' => $other->member_id])
            ->get(route('member.support.attachments.show', $attachment))
            ->assertNotFound();
        auth()->logout();
        $this->flushSession();
        $this->get(route('member.support.attachments.show', $attachment))->assertForbidden();

        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.support.store'), $this->ticketPayload((string) Str::uuid(), [
                'attachment' => UploadedFile::fake()->create('shell.php', 20, 'text/plain'),
            ]))
            ->assertSessionHasErrors('attachment');
        $this->assertSame(1, SupportTicket::query()->count());
    }

    public function test_unavailable_mail_still_saves_the_in_app_notice(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);
        $member = $this->member('ST200030');
        User::factory()->create(['is_admin' => true]);

        $response = $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.support.store'), $this->ticketPayload((string) Str::uuid()));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertStringContainsString('Email was not sent', (string) $response->getSession()->get('success'));
        Mail::assertNothingSent();
        $this->assertSame(SupportNotification::EMAIL_UNAVAILABLE, SupportNotification::query()->value('email_status'));
        $this->assertSame(1, SupportTicket::query()->count());
    }

    public function test_admin_filters_and_member_history_are_paginated(): void
    {
        $member = $this->member('ST200040');
        $other = $this->member('ST200041');
        foreach (range(1, 16) as $index) {
            SupportTicket::query()->create([
                'reference' => 'TKT'.str_pad((string) $index, 6, '0', STR_PAD_LEFT),
                'member_id' => $member->member_id,
                'subject' => 'Ticket '.$index,
                'category' => 'wallet',
                'priority' => 'normal',
                'status' => SupportTicket::OPEN,
                'idempotency_key' => (string) Str::uuid(),
            ]);
        }
        SupportTicket::query()->create([
            'reference' => 'TKT000017',
            'member_id' => $other->member_id,
            'subject' => 'Payment ticket',
            'category' => 'payment',
            'priority' => 'normal',
            'status' => SupportTicket::OPEN,
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $first = SupportTicket::query()->where('reference', 'TKT000001')->firstOrFail();
        foreach (range(1, 21) as $index) {
            SupportMessage::query()->create([
                'support_ticket_id' => $first->id,
                'sender_role' => SupportMessage::ROLE_MEMBER,
                'sender_member_id' => $member->member_id,
                'body' => $index === 1 ? 'First thread page' : ($index === 21 ? 'Last thread page' : 'Middle thread note '.$index),
                'idempotency_key' => (string) Str::uuid(),
            ]);
        }

        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.support', ['page' => 2]))
            ->assertOk()
            ->assertSeeText('Ticket 1');
        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.support.show', ['supportTicket' => $first, 'page' => 2]))
            ->assertOk()
            ->assertSeeText('Last thread page')
            ->assertDontSeeText('First thread page');
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)
            ->get(route('admin.support.index', [
                'member_id' => $other->member_id,
                'category' => 'payment',
                'status' => 'open',
                'priority' => 'normal',
                'from_date' => now()->toDateString(),
                'to_date' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSeeText('TKT000017')
            ->assertDontSeeText('TKT000001');
    }

    public function test_ticket_message_is_escaped(): void
    {
        Mail::fake();
        $member = $this->member('ST200050');
        User::factory()->create(['is_admin' => true]);
        $this->withSession(['member_context_id' => $member->member_id])
            ->post(route('member.support.store'), $this->ticketPayload((string) Str::uuid(), [
                'message' => '<script>alert(1)</script>',
            ]))
            ->assertRedirect();

        $ticket = SupportTicket::query()->firstOrFail();
        $this->withSession(['member_context_id' => $member->member_id])
            ->get(route('member.support.show', $ticket))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSeeText('<script>alert(1)</script>');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function ticketPayload(string $key = '11111111-1111-4111-8111-111111111111', array $extra = []): array
    {
        return array_merge([
            'subject' => 'Wallet transfer did not show',
            'category' => 'transfer',
            'priority' => 'normal',
            'message' => 'The transfer receipt is missing from my history.',
            'idempotency_key' => $key,
        ], $extra);
    }

    private function member(string $memberId): Member
    {
        return Member::query()->create([
            'member_id' => $memberId,
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'Member '.$memberId,
            'mobile_no' => '9000001111',
            'pan_card_no' => 'ABCDE1234F',
            'email' => strtolower($memberId).'@example.test',
            'status' => 'active',
            'working_wallet_amount' => '0.0000',
            'roi_wallet_amount' => '0.0000',
            'activation_wallet_amount' => '0.0000',
        ]);
    }
}
