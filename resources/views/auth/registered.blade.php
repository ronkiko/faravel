@extends('layouts.theme')

@section('content')
    <h1>Registration Successful</h1>
    <p>Welcome, {{ $username }}!</p>
    <p>You can now <a href="/login">log in</a>.</p>
@endsection
