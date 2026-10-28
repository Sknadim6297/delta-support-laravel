<div class="ds-table-wrap">
    <table class="{{ $kind === 'level' ? 'ds-table-wide' : '' }}">
        <thead>
            <tr>
                @if($audience === 'admin')
                    <th>Member</th>
                @endif
                <th>Reference</th>
                <th>Date</th>
                <th class="ds-money">Amount</th>
                <th>Package reference</th>
                <th>Status</th>
                @if($kind === 'level')
                    <th>Level</th>
                    <th>Source member</th>
                @endif
            </tr>
        </thead>
        <tbody>
        @forelse($rows as $row)
            <tr>
                @if($audience === 'admin')
                    <td>
                        <span class="ds-person">
                            <strong>{{ $row->member_id }}</strong>
                            <small>{{ $row->member_name }}</small>
                        </span>
                    </td>
                @endif
                <td>{{ $row->reference }}</td>
                <td>{{ $kind === 'roi' ? ($row->roi_date instanceof \DateTimeInterface ? $row->roi_date->format('d M Y') : ($row->roi_date ? \Carbon\CarbonImmutable::parse(substr((string) $row->roi_date, 0, 10))->format('d M Y') : '')) : ($row->business_date instanceof \DateTimeInterface ? $row->business_date->format('d M Y') : ($row->business_date ? \Carbon\CarbonImmutable::parse(substr((string) $row->business_date, 0, 10))->format('d M Y') : ($row->created_at?->format('d M Y') ?? ''))) }}</td>
                <td class="ds-money">{{ \App\Support\MoneyDisplay::usdt($row->income_amount) }}</td>
                <td>{{ $row->package_label ?? 'Not linked to a package' }}</td>
                    <td>
                    @php($statusLabel = $kind === 'roi' ? ucfirst((string) ($row->status ?: 'recorded')) : 'Recorded')
                    <x-ui.badge :tone="$statusLabel === 'Recorded' ? 'brand' : 'muted'">{{ $statusLabel }}</x-ui.badge>
                    @if (($row->origin_label ?? '') === 'Local test')
                        <x-ui.badge tone="warning">Local test</x-ui.badge>
                    @endif
                </td>
                @if($kind === 'level')
                    <td>{{ $row->level }}</td>
                    <td>{{ $row->from_member_id }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td class="ds-empty-cell" colspan="{{ ($audience === 'admin' ? 6 : 5) + ($kind === 'level' ? 2 : 0) }}">
                    <span>{{ $kind === 'roi' ? 'No ROI records found.' : 'No level income records found.' }}</span>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="ds-pagination">{{ $rows->links('pagination::bootstrap-5') }}</div>
