<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\SupportAttachment;
use App\Models\SupportNotification;
use App\Models\SupportTicket;
use App\Services\SupportTicketWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $member = $this->member($request);
        $tickets = SupportTicket::query()
            ->where('member_id', $member->member_id)
            ->latest('id')
            ->paginate(15);

        return view('member.support.index', [
            'member' => $member,
            'tickets' => $tickets,
        ]);
    }

    public function create(Request $request): View
    {
        return view('member.support.create', [
            'member' => $this->member($request),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, SupportTicketWorkflow $workflow): RedirectResponse
    {
        $member = $this->member($request);
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(SupportTicket::CATEGORY_LABELS))],
            'priority' => ['required', Rule::in(SupportTicket::PRIORITIES)],
            'message' => ['required', 'string', 'max:5000'],
            'idempotency_key' => ['required', 'uuid'],
            'attachment' => ['nullable', 'file', 'max:2048', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);

        try {
            $ticket = $workflow->open($member, $validated, $request->file('attachment'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['ticket' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('member.support.show', $ticket)
            ->with('success', 'Ticket '.$ticket->reference.' was saved. '.$this->notice($ticket));
    }

    public function show(Request $request, SupportTicket $supportTicket): View
    {
        $member = $this->member($request);
        abort_unless($supportTicket->member_id === $member->member_id, 404);
        $this->markRead($supportTicket, $member->member_id, null);

        return view('member.support.show', [
            'member' => $member,
            'ticket' => $supportTicket,
            'messages' => $supportTicket->messages()->with('attachment')->paginate(20),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function reply(Request $request, SupportTicket $supportTicket, SupportTicketWorkflow $workflow): RedirectResponse
    {
        $member = $this->member($request);
        abort_unless($supportTicket->member_id === $member->member_id, 404);
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'idempotency_key' => ['required', 'uuid'],
            'attachment' => ['nullable', 'file', 'max:2048', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);

        try {
            $workflow->replyAsMember($supportTicket, $member, $validated['message'], $validated['idempotency_key'], $request->file('attachment'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['message' => $exception->getMessage()]);
        }

        return redirect()
            ->route('member.support.show', $supportTicket)
            ->with('success', 'Your reply was saved. '.$this->notice($supportTicket));
    }

    public function close(Request $request, SupportTicket $supportTicket, SupportTicketWorkflow $workflow): RedirectResponse
    {
        $member = $this->member($request);
        abort_unless($supportTicket->member_id === $member->member_id, 404);
        $workflow->closeAsMember($supportTicket, $member);

        return redirect()
            ->route('member.support.show', $supportTicket)
            ->with('success', 'Ticket '.$supportTicket->reference.' is closed.');
    }

    public function attachment(Request $request, SupportAttachment $supportAttachment): StreamedResponse
    {
        $member = $this->member($request);
        $supportAttachment->load('message.ticket');
        abort_unless($supportAttachment->message?->ticket?->member_id === $member->member_id, 404);

        return $this->download($supportAttachment);
    }

    private function member(Request $request): Member
    {
        return Member::query()
            ->where('member_id', $request->session()->get('member_context_id'))
            ->firstOrFail();
    }

    private function markRead(SupportTicket $ticket, ?string $memberId, ?int $userId): void
    {
        SupportNotification::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('audience', SupportNotification::AUDIENCE_MEMBER)
            ->where('member_id', $memberId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        unset($userId);
    }

    private function notice(SupportTicket $ticket): string
    {
        $statuses = SupportNotification::query()
            ->where('support_ticket_id', $ticket->id)
            ->pluck('email_status');
        if ($statuses->contains(SupportNotification::EMAIL_SENT)) {
            return 'An email notice was sent.';
        }
        if ($statuses->contains(SupportNotification::EMAIL_FAILED)) {
            return 'The email notice could not be delivered.';
        }

        return 'Email was not sent because mail delivery is unavailable.';
    }

    private function download(SupportAttachment $attachment): StreamedResponse
    {
        $path = $attachment->path;
        abort_unless(
            $attachment->disk === 'local'
            && str_starts_with($path, 'support-attachments/')
            && ! str_contains($path, '..')
            && Storage::disk('local')->exists($path),
            404
        );

        return Storage::disk('local')->download($path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
