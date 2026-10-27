<div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                @if($audience === 'admin')
                    <th class="p-3">Member</th>
                @endif
                <th class="p-3">Reference</th>
                <th class="p-3">Date</th>
                <th class="p-3">Amount</th>
                <th class="p-3">Package reference</th>
                <th class="p-3">Status</th>
                @if($kind === 'level')
                    <th class="p-3">Level</th>
                    <th class="p-3">Source member</th>
                @endif
            </tr>
        </thead>
        <tbody>
        @forelse($rows as $row)
            <tr class="border-t border-slate-100">
                @if($audience === 'admin')
                    <td class="p-3"><span class="font-semibold">{{ $row->member_id }}</span><br><span class="text-ink-500">{{ $row->member_name }}</span></td>
                @endif
                <td class="p-3 font-semibold">{{ $row->reference }}</td>
                <td class="p-3">{{ $kind === 'roi' ? $row->roi_date?->toDateString() : ($row->business_date?->toDateString() ?? $row->created_at?->toDateString()) }}</td>
                <td class="p-3">{{ \App\Support\MoneyDisplay::usdt($row->income_amount) }}</td>
                <td class="p-3">Not linked to a package</td>
                <td class="p-3">{{ $kind === 'roi' ? ucfirst((string) ($row->status ?: 'recorded')) : 'Recorded' }}</td>
                @if($kind === 'level')
                    <td class="p-3">{{ $row->level }}</td>
                    <td class="p-3">{{ $row->from_member_id }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td class="p-6 text-slate-500" colspan="{{ ($audience === 'admin' ? 6 : 5) + ($kind === 'level' ? 2 : 0) }}">
                    {{ $kind === 'roi' ? 'No ROI records yet.' : 'No level income records yet.' }}
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $rows->links('pagination::bootstrap-5') }}</div>
