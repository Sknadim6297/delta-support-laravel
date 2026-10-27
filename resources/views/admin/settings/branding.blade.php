@extends('admin.layouts.app')
@section('content')
<div>
    <header>
        <h1>Branding</h1>
        <p>The public site and both panels use these colors and the current logo.</p>
    </header>
    <div>
        <img src="{{ asset('site/img/logo.png') }}" alt="Delta Support">
        <dl>
            <div><dt>Primary</dt><dd>#0878d1</dd></div>
            <div><dt>Navy</dt><dd>#092747</dd></div>
        </dl>
    </div>
</div>
@endsection
