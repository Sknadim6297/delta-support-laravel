@extends('admin.layouts.app')
@section('content')
<div>
    <header>
        <h1>Commission Settings</h1>
        <p>Stored Delta Support plan only. The plan stays inactive, and this page does not generate level income.</p>
    </header>
    <div>
        <article><p>Levels stored</p><p>{{ $levels->count() }}</p></article>
        <article><p>Combined rate</p><p>{{ $total }}%</p></article>
        <article><p>Plan status</p><p>{{ $active ? 'Active' : 'Inactive' }}</p></article>
    </div>
    @if ($errors !== [])
        <div>
            @foreach ($errors as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif
    <div>
        <table>
            <thead><tr><th>Level</th><th>Percentage</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($levels as $level)
                <tr>
                    <td>{{ $level->level }}</td>
                    <td>{{ $level->percentage }}%</td>
                    <td>{{ $level->is_active ? 'Active' : 'Inactive' }}</td>
                </tr>
            @empty
                <tr><td colspan="3">No commission plan is stored.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
