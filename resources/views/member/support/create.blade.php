@extends('member.layouts.app')
@section('title', 'New Ticket')
@section('page-title', 'New Ticket')

@section('content')
<x-members.shell kicker="Support" lead="Describe the issue. One optional JPG, PNG, WebP, or PDF up to 2 MB can be attached.">
    @if ($errors->any())
        <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
    @endif
    <form method="POST" action="{{ route('member.support.store') }}" enctype="multipart/form-data" class="ds-mm-form">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
        <x-members.panel title="Ticket details">
            <div class="ds-mm-fields">
                <label><span>Subject</span><input name="subject" value="{{ old('subject') }}" maxlength="150" required></label>
                <label><span>Category</span>
                    <select name="category" required>
                        <option value="">Choose a category</option>
                        @foreach (\App\Models\SupportTicket::CATEGORY_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label><span>Priority</span>
                    <select name="priority" required>
                        @foreach (\App\Models\SupportTicket::PRIORITIES as $priority)
                            <option value="{{ $priority }}" @selected(old('priority', 'normal') === $priority)>{{ ucfirst($priority) }}</option>
                        @endforeach
                    </select>
                </label>
                <label><span>Message</span><textarea name="message" rows="6" maxlength="5000" required>{{ old('message') }}</textarea></label>
                <label><span>Attachment</span><input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"></label>
            </div>
            <div class="ds-form-actions">
                <button type="submit">Submit ticket</button>
                <a class="ds-btn ds-btn-secondary" href="{{ route('member.support') }}">My tickets</a>
            </div>
        </x-members.panel>
    </form>
</x-members.shell>
@endsection
