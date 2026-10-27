@extends('admin.layouts.app')

@section('content')
    <main>
        <div>
            @if (session('success'))
                <div>{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div>{{ $errors->first() }}</div>
            @endif

            <div>
                <h4>{{ $rank->exists ? 'Edit Rank' : 'Rank Management' }}</h4>
            </div>

            <form method="POST" action="{{ $rank->exists ? route('admin.ranks.update', $rank) : route('admin.ranks.store') }}">
                @csrf
                @if ($rank->exists)
                    @method('PUT')
                @endif
                <div>
                    <div>
                        <label for="rankName">Rank Name</label>
                        <input id="rankName" name="name" value="{{ old('name', $rank->name) }}" required>
                    </div>
                    <div>
                        <label for="rankBusiness">Required Business</label>
                        <input id="rankBusiness" name="required_full_team_business" type="number" min="0" step="0.0001" value="{{ old('required_full_team_business', $rank->required_full_team_business) }}" required>
                    </div>
                    <div>
                        <label for="rankLevels">Unlocked Levels</label>
                        <input id="rankLevels" name="unlocked_levels" type="number" min="1" max="32" value="{{ old('unlocked_levels', $rank->unlocked_levels) }}" required>
                    </div>
                    <div>
                        <label for="rankOrder">Sort Order</label>
                        <input id="rankOrder" name="sort_order" type="number" min="1" max="255" value="{{ old('sort_order', $rank->sort_order) }}" required>
                    </div>
                    <div>
                        <label for="rankStatus">Status</label>
                        <select id="rankStatus" name="is_active" required>
                            <option value="1" @selected((string) old('is_active', $rank->is_active ? '1' : '0') === '1')>Active</option>
                            <option value="0" @selected((string) old('is_active', $rank->is_active ? '1' : '0') === '0')>Inactive</option>
                        </select>
                    </div>
                </div>
                <div>
                    @if ($rank->exists)
                        <a href="{{ route('admin.ranks.index') }}">Cancel</a>
                    @endif
                    <button type="submit">{{ $rank->exists ? 'Update Rank' : 'Create Rank' }}</button>
                </div>
            </form>

            <div>
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Required Business</th>
                            <th>Unlocked Levels</th>
                            <th>Status</th>
                            <th>Sort Order</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ranks as $configuredRank)
                            <tr>
                                <td>{{ $configuredRank->name }}</td>
                                <td>{{ \App\Support\MoneyDisplay::usdt($configuredRank->required_full_team_business) }}</td>
                                <td>1 - {{ $configuredRank->unlocked_levels }}</td>
                                <td>{{ $configuredRank->is_active ? 'Active' : 'Inactive' }}</td>
                                <td>{{ $configuredRank->sort_order }}</td>
                                <td>
                                    <div>
                                        <a href="{{ route('admin.ranks.edit', $configuredRank) }}">Edit</a>
                                        <form method="POST" action="{{ route('admin.ranks.destroy', $configuredRank) }}" onsubmit="return confirm('Are you sure you want to delete this rank?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No ranks configured.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
@endsection
