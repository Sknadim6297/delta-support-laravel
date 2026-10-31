<?php

namespace Tests\Feature;

use App\Mail\MemberPasswordSetupMail;
use App\Models\Member;
use App\Models\User;
use App\Services\MemberPasswordSetupMailer;
use App\Services\SmtpMailTranscript;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mailer\Envelope as MailerEnvelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;

class MemberRegistrationMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_smtp_success_authentication_failure_and_timeout_are_distinct(): void
    {
        $transcript = new SmtpMailTranscript;
        $accepted = $transcript->interpret('smtp', "220 ready\n235 Authenticated\n354 Start mail input\n250 Requested mail action okay, completed\n");

        $this->assertTrue($accepted->accepted);
        $this->assertStringContainsString('Inbox delivery is not confirmed', $accepted->sentence('member@example.com'));
        $this->assertStringNotContainsString('password was sent', strtolower($accepted->sentence('member@example.com')));

        $authentication = $transcript->interpret('smtp', null, new RuntimeException('Expected response code 235 but got code 535, Failed to authenticate'));
        $this->assertFalse($authentication->accepted);
        $this->assertSame('authentication', $authentication->status);
        $this->assertStringContainsString('rejected the sender login', $authentication->sentence('member@example.com'));

        $timeout = $transcript->interpret('smtp', null, new RuntimeException('Connection to the mail host timed out'));
        $this->assertFalse($timeout->accepted);
        $this->assertSame('timeout', $timeout->status);
        $this->assertStringContainsString('did not respond in time', $timeout->sentence('member@example.com'));
    }

    public function test_registration_mailer_reads_the_smtp_transcript_and_does_not_queue(): void
    {
        $this->useTransport(function (RawMessage $message, ?MailerEnvelope $envelope = null): SentMessage {
            $sent = new SentMessage($message, $envelope ?? MailerEnvelope::create($message));
            $sent->appendDebug("354 Start mail input\n250 Requested mail action okay, completed\n");

            return $sent;
        });

        $member = $this->member();
        $notice = app(MemberPasswordSetupMailer::class)->send($member);

        $this->assertTrue($notice->accepted);
        $this->assertSame('accepted', $notice->status);
        $this->assertNotInstanceOf(ShouldQueue::class, new MemberPasswordSetupMail('ST100002', 'New Member', 'https://example.test/setup'));
    }

    public function test_authentication_and_timeout_exceptions_do_not_claim_acceptance(): void
    {
        $member = $this->member();

        $this->useTransport(function (): never {
            throw new TransportException('Failed to authenticate on SMTP server');
        });
        $authentication = app(MemberPasswordSetupMailer::class)->send($member);
        $this->assertSame('authentication', $authentication->status);

        $this->useTransport(function (): never {
            throw new TransportException('Connection timed out');
        });
        $timeout = app(MemberPasswordSetupMailer::class)->send($member->fresh());
        $this->assertSame('timeout', $timeout->status);
        $this->assertStringNotContainsString('password was sent', strtolower($timeout->sentence($member->email)));
    }

    public function test_admin_registration_shows_smtp_acceptance_once_and_clears_it_on_refresh(): void
    {
        $this->useTransport(function (RawMessage $message, ?MailerEnvelope $envelope = null): SentMessage {
            $sent = new SentMessage($message, $envelope ?? MailerEnvelope::create($message));
            $sent->appendDebug("354 Start mail input\n250 Requested mail action okay, completed\n");

            return $sent;
        });

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.members.store'), $this->registrationPayload('accepted@example.com'))
            ->assertRedirect(route('admin.members.registration'))
            ->assertSessionMissing('success')
            ->assertSessionMissing('mail_notice')
            ->assertSessionHas('registration_notice.tone', 'success')
            ->assertSessionHas('registration_notice.email_status', 'Mail server accepted the password setup email. Inbox delivery is not confirmed.');

        $member = Member::query()->where('email', 'accepted@example.com')->firstOrFail();
        $page = $this->get(route('admin.members.registration'));
        $page->assertOk()
            ->assertSeeText('Registration successful')
            ->assertSeeText('Member ID: '.$member->member_id)
            ->assertSeeText('Mail server accepted the password setup email. Inbox delivery is not confirmed.')
            ->assertSee('ds-registration-notice is-success', false)
            ->assertDontSee('ds-registration-notice is-warning', false);
        $this->assertSame(1, substr_count($page->getContent(), 'Registration successful'));
        $this->assertSame(1, substr_count($page->getContent(), 'Mail server accepted the password setup email. Inbox delivery is not confirmed.'));

        $this->get(route('admin.members.registration'))
            ->assertOk()
            ->assertDontSeeText('Registration successful')
            ->assertDontSeeText('Mail server accepted the password setup email.');

        $count = Member::query()->count();
        $this->from(route('admin.members.registration'))
            ->post(route('admin.members.password-setup-email'), ['member_id' => $member->member_id])
            ->assertRedirect(route('admin.members.registration'))
            ->assertSessionMissing('success')
            ->assertSessionHas('registration_notice.title', 'No new member was created.')
            ->assertSessionHas('registration_notice.tone', 'success');
        $this->assertSame($count, Member::query()->count());
    }

    public function test_admin_registration_shows_a_warning_when_smtp_authentication_fails(): void
    {
        $this->useTransport(function (): never {
            throw new TransportException('Failed to authenticate on SMTP server');
        });

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post(route('admin.members.store'), $this->registrationPayload('rejected@example.com'))
            ->assertRedirect(route('admin.members.registration'))
            ->assertSessionMissing('success')
            ->assertSessionHas('registration_notice.tone', 'warning')
            ->assertSessionHas('registration_notice.email_status', 'The mail server rejected the sender login. The password setup email was not accepted.');

        $member = Member::query()->where('email', 'rejected@example.com')->firstOrFail();
        $page = $this->get(route('admin.members.registration'));
        $page->assertOk()
            ->assertSeeText('Registration successful')
            ->assertSeeText('Member ID: '.$member->member_id)
            ->assertSeeText('The mail server rejected the sender login. The password setup email was not accepted.')
            ->assertSee('ds-registration-notice is-warning', false)
            ->assertDontSee('ds-registration-notice is-success', false);
        $this->assertSame(1, substr_count($page->getContent(), 'ds-registration-notice is-warning'));
    }

    public function test_a_queued_mailable_is_not_reported_as_accepted(): void
    {
        Mail::fake();
        $notice = app(MemberPasswordSetupMailer::class)->send($this->member(), new QueuedRegistrationProbeMail);

        $this->assertFalse($notice->accepted);
        $this->assertSame('queued', $notice->status);
        $this->assertStringContainsString('has not been accepted', $notice->sentence('new@example.com'));
        Mail::assertNothingSent();
    }

    private function registrationPayload(string $email): array
    {
        return [
            'member_name' => 'New Member',
            'sponsor_id' => 'ST666666',
            'email' => $email,
            'mobile_no' => '9876543212',
            'pan_card_no' => 'ABCDE1234F',
        ];
    }

    private function member(): Member
    {
        return Member::query()->create([
            'member_id' => 'ST100002',
            'sponsor_id' => 'ST666666',
            'sponsor_name' => 'Admin',
            'member_name' => 'New Member',
            'email' => 'new@example.com',
            'mobile_no' => '9876543212',
            'status' => 'inactive',
        ]);
    }

    private function useTransport(callable $send): void
    {
        Mail::extend('probe', function () use ($send): TransportInterface {
            return new class($send) implements TransportInterface
            {
                public function __construct(private $send) {}

                public function send(RawMessage $message, ?MailerEnvelope $envelope = null): ?SentMessage
                {
                    return ($this->send)($message, $envelope);
                }

                public function __toString(): string
                {
                    return 'probe';
                }
            };
        });

        config([
            'mail.default' => 'probe',
            'mail.mailers.probe.transport' => 'probe',
        ]);
        Mail::purge('probe');
    }
}

class QueuedRegistrationProbeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Queued probe');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>queued</p>');
    }
}
