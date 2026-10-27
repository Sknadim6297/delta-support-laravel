@extends('admin.layouts.app')

@section('content')
    <main class="dashboard-content">
        <div class="form-card">
            @include('partials.legacy-financial-lock')
            @if (session('success'))
                <div class="alert alert-success mb-4">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger mb-4">{{ $errors->first() }}</div>
            @endif

            <div class="form-title">
                <h4>Level Commission Statement</h4>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover member-table">
                    <thead>
                        <tr>
                            <th>Level</th>
                            <th>Commission Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rates as $rate)
                            <tr>
                                <td>Level {{ $rate['level'] }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.level-commission.statement.update', ['levelCommission' => $rate['id']]) }}" class="d-inline-flex align-items-center gap-2">
                                        @csrf
                                        <div class="input-group">
                                            <input type="number"
                                                   name="percentage"
                                                   value="{{ old('percentage', $rate['percentage']) }}"
                                                   class="form-control"
                                                   min="0"
                                                   max="100"
                                                   step="0.0001"
                                                   required>
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <button type="submit"
                                                class="btn btn-primary"
                                                onclick="return confirm('Are you sure to update this?');">
                                            Update
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </main>
@endsection
