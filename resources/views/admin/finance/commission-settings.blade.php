@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl">
    <header class="mb-4">
        <h1 class="ds-page-title">Commission Settings</h1>
        <p class="ds-page-copy">Stored Delta Support plan only. The plan stays inactive, and this page does not generate level income.</p>
    </header>
    <div class="mb-4 grid gap-3 sm:grid-cols-3">
        <article class="ds-card"><p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Levels stored</p><p class="mt-2 text-xl font-semibold text-navy-900">{{ $levels->count() }}</p></article>
        <article class="ds-card"><p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Combined rate</p><p class="mt-2 text-xl font-semibold text-navy-900">{{ $total }}%</p></article>
        <article class="ds-card"><p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Plan status</p><p class="mt-2 text-xl font-semibold text-navy-900">{{ $active ? 'Active' : 'Inactive' }}</p></article>
    </div>
    @if ($errors !== [])
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
            @foreach ($errors as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-navy-900"><tr><th class="p-3">Level</th><th class="p-3">Percentage</th><th class="p-3">Status</th></tr></thead>
            <tbody>
            @forelse ($levels as $level)
                <tr class="border-t border-slate-200">
                    <td class="p-3">{{ $level->level }}</td>
                    <td class="p-3">{{ $level->percentage }}%</td>
                    <td class="p-3">{{ $level->is_active ? 'Active' : 'Inactive' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="p-6 text-slate-500">No commission plan is stored.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
