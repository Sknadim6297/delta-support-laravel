@extends('admin.layouts.app')
@section('content')
<div>
    <header>
        <h1>Admin Profile</h1>
        <p>The account currently signed in to the admin panel.</p>
    </header>
    <div>
        <dl>
            <div><dt>Name</dt><dd>{{ $user->name }}</dd></div>
            <div><dt>Email</dt><dd>{{ $user->email }}</dd></div>
        </dl>
    </div>
</div>
@endsection
