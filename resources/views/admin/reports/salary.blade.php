@extends('admin.layouts.app')

@section('title', 'Salary Report')
@section('page-title', 'Salary Report')

@section('content')
<div class="ds-reports">
    <x-ui.page-header lead="Salary report records are not available on this panel yet." />
    <x-ui.empty title="No salary report data" message="This report has no stored rows to display." />
</div>
@endsection
