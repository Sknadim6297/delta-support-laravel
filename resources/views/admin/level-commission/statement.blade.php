@extends('admin.layouts.app')

@section('content')
    <main>
        <div>
            @include('partials.legacy-financial-lock')
            @if (session('success'))
                <div>{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div>{{ $errors->first() }}</div>
            @endif

            <div>
                <h4>Level Commission Statement</h4>
            </div>

            <div>
                <table>
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
                                    <form method="POST" action="{{ route('admin.level-commission.statement.update', ['levelCommission' => $rate['id']]) }}">
                                        @csrf
                                        <div>
                                            <input type="number"
                                                   name="percentage"
                                                   value="{{ old('percentage', $rate['percentage']) }}"
                                                  
                                                   min="0"
                                                   max="100"
                                                   step="0.0001"
                                                   required>
                                            <span>%</span>
                                        </div>
                                        <button type="submit"
                                               
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
