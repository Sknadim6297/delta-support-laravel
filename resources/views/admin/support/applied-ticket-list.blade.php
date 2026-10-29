@extends('admin.layouts.app')
@section('title', 'Support Tickets')
@section('content')
<p><a href="{{ route('admin.support.applied-tickets') }}">Open resolved tickets</a></p>
@endsection