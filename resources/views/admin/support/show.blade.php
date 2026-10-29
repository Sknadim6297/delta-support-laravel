@extends('admin.layouts.app')
@section('title', $ticket->reference)
@section('page-title', $ticket->reference)

@section('content')
<div class="ds-finance">
    <x-ui.page-header kicker="Support" :title="$ticket->reference" :lead="$ticket->subject" />
    @if ($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif
    <x-ui.card title="Ticket">
        <dl>
            <div><dt>Member</dt><dd>{{ $ticket->member_id }} · {{ $ticket->member?->member_name }}</dd></div>
            <div><dt>Category</dt><dd>{{ $ticket->categoryLabel() }}</dd></div>
            <div><dt>Priority</dt><dd>{{ ucfirst($ticket->priority) }}</dd></div>
            <div><dt>Status</dt><dd><x-ui.badge :tone="$ticket->statusTone()">{{ $ticket->statusLabel() }}</x-ui.badge></dd></div>
            <div><dt>Opened</dt><dd>{{ $ticket->created_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') }} IST</dd></div>
        </dl>
        <form method="POST" action="{{ route('admin.support.status', $ticket) }}">
            @csrf
            <label><span>Change status</span>
                <select name="status" required>
                    <option value="{{ \App\Models\SupportTicket::AWAITING_ADMIN }}">Awaiting Admin / Reopen</option>
                    <option value="{{ \App\Models\SupportTicket::AWAITING_MEMBER }}">Awaiting Member</option>
                    <option value="{{ \App\Models\SupportTicket::RESOLVED }}">Resolved</option>
                    <option value="{{ \App\Models\SupportTicket::CLOSED }}">Closed</option>
                </select>
            </label>
            <div class="ds-form-actions"><button type="submit">Update status</button></div>
        </form>
    </x-ui.card>

    @include('partials.support-thread-style')
    <x-ui.card title="Conversation">
        <div class="ds-ticket-thread">
            @foreach ($messages as $message)
                <article class="ds-ticket-message {{ $message->sender_role === 'admin' ? 'is-admin' : '' }}">
                    <p class="ds-ticket-meta"><strong>{{ $message->sender_role === 'admin' ? 'Admin' : $message->sender_member_id }}</strong> · {{ $message->created_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') }}</p>
                    <p>{{ $message->body }}</p>
                    @if ($message->attachment)
                        <p><a href="{{ route('admin.support.attachments.show', $message->attachment) }}">{{ $message->attachment->original_name }}</a></p>
                    @endif
                </article>
            @endforeach
        </div>
        <div class="ds-pagination">{{ $messages->links('pagination::bootstrap-5') }}</div>
    </x-ui.card>

    @if (! $ticket->isClosed())
        <form method="POST" action="{{ route('admin.support.reply', $ticket) }}" enctype="multipart/form-data" class="ds-ticket-reply">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
            <x-ui.card title="Reply">
                <label><span>Message</span><textarea name="message" rows="5" maxlength="5000" required>{{ old('message') }}</textarea></label>
                <label><span>Attachment</span><input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"></label>
                <div class="ds-form-actions"><button type="submit">Send reply</button></div>
            </x-ui.card>
        </form>
    @else
        <x-ui.alert>Reopen the ticket before sending another reply.</x-ui.alert>
    @endif
    <p><a href="{{ route('admin.support.index') }}">All tickets</a></p>
</div>
@endsection
