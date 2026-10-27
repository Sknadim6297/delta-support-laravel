@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-6xl">
    <h1 class="mb-4 text-2xl font-bold text-navy-900">All Members</h1>
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Member ID, name, or email" class="rounded-lg border px-3 py-2">
        <select name="status" class="rounded-lg border px-3 py-2">
            <option value="">All</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <button class="rounded-lg bg-navy-900 px-4 py-2 text-sm font-semibold text-white">Search</button>
    </form>
    <div class="overflow-x-auto rounded-xl border bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left"><tr><th class="p-3">Member ID</th><th class="p-3">Name</th><th class="p-3">Sponsor</th><th class="p-3">Account</th></tr></thead>
            <tbody>
            @forelse($members as $member)
                <tr class="border-t">
                    <td class="p-3">{{ $member->member_id }}</td>
                    <td class="p-3">{{ $member->member_name }}</td>
                    <td class="p-3">{{ $member->sponsor_id }}</td>
                    <td class="p-3">
                        <form method="POST" action="{{ route('admin.members.status', $member->member_id) }}" class="flex items-center gap-2">
                            @csrf
                            @method('PATCH')
                            <select name="status" class="rounded border px-2 py-1" aria-label="Account status for {{ $member->member_id }}">
                                <option value="active" @selected($member->status === 'active')>Active</option>
                                <option value="inactive" @selected($member->status === 'inactive')>Inactive</option>
                            </select>
                            <button class="rounded bg-navy-900 px-3 py-1 text-white">Save</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="p-6 text-slate-500">No members yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $members->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
