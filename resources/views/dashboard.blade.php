@extends('layouts.theme')

@section('content')
    <h1>Dashboard</h1>
    @if(isset($user) && $user)
        <p>
            Welcome,
            {{ isset($user['username']) ? $user['username'] : (isset($user['name']) ? $user['name'] : 'User') }}!
        </p>
    @else
        <p>Welcome, guest.</p>
    @endif
@endsection
