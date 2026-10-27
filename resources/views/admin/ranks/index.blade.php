@extends('admin.layouts.app')

@section('content')
    <main class="dashboard-content">
        <div class="form-card">
            @if (session('success'))
                <div class="alert alert-success mb-4">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger mb-4">{{ $errors->first() }}</div>
            @endif

            <div class="form-title">
                <h4>{{ $rank->exists ? 'Edit Rank' : 'Rank Management' }}</h4>
            </div>

            <form method="POST" action="{{ $rank->exists ? route('admin.ranks.update', $rank) : route('admin.ranks.store') }}" class="mb-4">
                @csrf
                @if ($rank->exists)
                    @method('PUT')
                @endif
                <div class="row align-items-end g-3">
                    <div class="col-md-3">
                        <label for="rankName">Rank Name</label>
                        <input id="rankName" name="name" class="form-control" value="{{ old('name', $rank->name) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label for="rankBusiness">Required Business</label>
                        <input id="rankBusiness" name="required_full_team_business" type="number" min="0" step="0.0001" class="form-control" value="{{ old('required_full_team_business', $rank->required_full_team_business) }}" required>
                    </div>
                    <div class="col-md-2">
                        <label for="rankLevels">Unlocked Levels</label>
                        <input id="rankLevels" name="unlocked_levels" type="number" min="1" max="32" class="form-control" value="{{ old('unlocked_levels', $rank->unlocked_levels) }}" required>
                    </div>
                    <div class="col-md-2">
                        <label for="rankOrder">Sort Order</label>
                        <input id="rankOrder" name="sort_order" type="number" min="1" max="255" class="form-control" value="{{ old('sort_order', $rank->sort_order) }}" required>
                    </div>
                    <div class="col-md-2">
                        <label for="rankStatus">Status</label>
                        <select id="rankStatus" name="is_active" class="form-control" required>
                            <option value="1" @selected((string) old('is_active', $rank->is_active ? '1' : '0') === '1')>Active</option>
                            <option value="0" @selected((string) old('is_active', $rank->is_active ? '1' : '0') === '0')>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    @if ($rank->exists)
                        <a href="{{ route('admin.ranks.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    @endif
                    <button type="submit" class="btn btn-primary">{{ $rank->exists ? 'Update Rank' : 'Create Rank' }}</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-hover member-table">
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
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="{{ route('admin.ranks.edit', $configuredRank) }}" class="btn btn-primary btn-sm">Edit</a>
                                        <form method="POST" action="{{ route('admin.ranks.destroy', $configuredRank) }}" onsubmit="return confirm('Are you sure you want to delete this rank?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center">No ranks configured.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
@endsection
