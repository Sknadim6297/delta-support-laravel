@extends('member.layouts.app')
@section('title', $ticket->reference)
@section('page-title', $ticket->reference)

@section('content')
<x-members.shell kicker="Support" lead="{{ $ticket->subject }}">
    @if ($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif
    <x-members.panel title="Ticket status" subtitle="{{ $ticket->categoryLabel() }} · {{ ucfirst($ticket->priority) }} priority">
        <div class="ds-mm-stats">
            <article class="ds-mm-stat">
                <span>Status</span>
                <strong><x-ui.badge :tone="$ticket->statusTone()">{{ $ticket->statusLabel() }}</x-ui.badge></strong>
                <small>Opened {{ $ticket->created_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') }} IST</small>
            </article>
        </div>
        @if (! $ticket->isClosed())
            <form method="POST" action="{{ route('member.support.close', $ticket) }}">
                @csrf
                <button type="submit" class="ds-btn ds-btn-secondary">Close ticket</button>
            </form>
        @endif
    </x-members.panel>

    @include('partials.support-thread-style')
    <x-members.panel title="Conversation">
        <div class="ds-ticket-thread">
            @foreach ($messages as $message)
                <article class="ds-ticket-message {{ $message->sender_role === 'admin' ? 'is-admin' : '' }}">
                    <p class="ds-ticket-meta"><strong>{{ $message->authorLabel() }}</strong> · {{ $message->created_at?->timezone('Asia/Kolkata')->format('d M Y, H:i') }}</p>
                    <p>{{ $message->body }}</p>
                    @if ($message->attachment)
                        <p><a href="{{ route('member.support.attachments.show', $message->attachment) }}">{{ $message->attachment->original_name }}</a></p>
                    @endif
                </article>
            @endforeach
        </div>
        <div class="ds-pagination">{{ $messages->links('pagination::bootstrap-5') }}</div>
    </x-members.panel>

    @if (in_array($ticket->status, [\App\Models\SupportTicket::OPEN, \App\Models\SupportTicket::AWAITING_ADMIN, \App\Models\SupportTicket::AWAITING_MEMBER], true))
        <form method="POST" action="{{ route('member.support.reply', $ticket) }}" enctype="multipart/form-data" class="ds-mm-form ds-ticket-reply">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
            <x-members.panel title="Reply">
                <div class="ds-mm-fields">
                    <label><span>Message</span><textarea name="message" rows="5" maxlength="5000" required>{{ old('message') }}</textarea></label>
                    <label><span>Attachment</span><input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"></label>
                </div>
                <button type="submit">Send reply</button>
            </x-members.panel>
        </form>
    @else
        <x-ui.alert>This ticket does not accept new replies.</x-ui.alert>
    @endif
</x-members.shell>
@endsection
