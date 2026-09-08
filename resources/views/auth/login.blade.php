@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
    <div class="card" style="max-width:420px;margin:24px auto">
        <h1>Sign in</h1>
        <p class="lede small">An MCP client is asking for access to your data. Sign in to continue to the consent screen.</p>

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">

            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">

            @error('email')
                <div class="error">{{ $message }}</div>
            @enderror

            <label style="display:flex;align-items:center;gap:8px;margin-top:14px">
                <input type="checkbox" name="remember" value="1"> Remember me
            </label>

            <div class="actions">
                <button class="primary" type="submit">Sign in</button>
            </div>
        </form>

        @if (app()->environment('local'))
            <p class="muted small" style="margin-top:18px">Demo account: <code>demo@example.com</code> / <code>password</code></p>
        @endif
    </div>
@endsection
