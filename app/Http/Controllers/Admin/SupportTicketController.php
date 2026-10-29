<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->filtered($request);

        return view('admin.support.index', [
            'tickets' => (clone $query)->latest('id')->paginate(20)->withQueryString(),
            'stats' => [
                'all' => SupportTicket::query()->count(),
                'open' => SupportTicket::query()->where('status', SupportTicket::OPEN)->count(),
                'awaiting_admin' => SupportTicket::query()->where('status', SupportTicket::AWAITING_ADMIN)->count(),
                'awaiting_member' => SupportTicket::query()->where('status', SupportTicket::AWAITING_MEMBER)->count(),
                'resolved' => SupportTicket::query()->where('status', SupportTicket::RESOLVED)->count(),
                'closed' => SupportTicket::query()->where('status', SupportTicket::CLOSED)->count(),
            ],
        ]);
    }

    public function show(Request $request, SupportTicket $supportTicket): View
    {
        SupportNotification::query()
            ->where('support_ticket_id', $supportTicket->id)
            ->where('audience', SupportNotification::AUDIENCE_ADMIN)
            ->where(function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id)->orWhereNull('user_id');
            })
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('admin.support.show', [
            'ticket' => $supportTicket->load('member'),
            'messages' => $supportTicket->messages()->with('attachment')->paginate(20),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function reply(Request $request, SupportTicket $supportTicket, SupportTicketWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'idempotency_key' => ['required', 'uuid'],
            'attachment' => ['nullable', 'file', 'max:2048', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);
        $workflow->replyAsAdmin($supportTicket, $request->user(), $validated['message'], $validated['idempotency_key'], $request->file('attachment'));

        return redirect()
            ->route('admin.support.show', $supportTicket)
            ->with('status', 'Your reply was saved.');
    }

    public function status(Request $request, SupportTicket $supportTicket, SupportTicketWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(SupportTicket::STATUSES)],
        ]);
        $workflow->changeStatus($supportTicket, $request->user(), $validated['status']);

        return redirect()
            ->route('admin.support.show', $supportTicket)
            ->with('status', 'The ticket status is now '.$supportTicket->fresh()->statusLabel().'.');
    }

    public function attachment(SupportAttachment $supportAttachment): StreamedResponse
    {
        $path = $supportAttachment->path;
        abort_unless(
            $supportAttachment->disk === 'local'
            && str_starts_with($path, 'support-attachments/')
            && ! str_contains($path, '..')
            && Storage::disk('local')->exists($path),
            404
        );

        return Storage::disk('local')->download($path, $supportAttachment->original_name, [
            'Content-Type' => $supportAttachment->mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function filtered(Request $request)
    {
        $validated = $request->validate([
            'member_id' => ['nullable', 'string', 'max:32'],
            'category' => ['nullable', Rule::in(array_keys(SupportTicket::CATEGORY_LABELS))],
            'status' => ['nullable', Rule::in(SupportTicket::STATUSES)],
            'priority' => ['nullable', Rule::in(SupportTicket::PRIORITIES)],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:20'],
        ]);

        return SupportTicket::query()
            ->when($validated['member_id'] ?? null, fn ($query, $memberId) => $query->where('member_id', trim($memberId)))
            ->when($validated['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['priority'] ?? null, fn ($query, $priority) => $query->where('priority', $priority))
            ->when($validated['reference'] ?? null, fn ($query, $reference) => $query->where('reference', trim($reference)))
            ->when($validated['from_date'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($validated['to_date'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to));
    }
}
