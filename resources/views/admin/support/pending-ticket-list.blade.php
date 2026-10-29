@extends('admin.layouts.app')
@section('title', 'Support Tickets')
@section('content')
<p><a href="{{ route('admin.support.pending-tickets') }}">Open the support ticket list</a></p>
@endsection
