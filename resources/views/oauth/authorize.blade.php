@extends('layouts.app')

@section('title', 'Authorize '.$client->name)

@section('content')
    <div class="card" style="max-width:520px;margin:24px auto">
        <h1>Authorize {{ $client->name }}</h1>
        <p class="lede small">
            <strong>{{ $client->name }}</strong> wants to access the MCP gateway as <strong>{{ $user->email }}</strong>.
            It will only be able to call tools covered by the scopes below.
        </p>

        @if (count($scopes) > 0)
            <table>
                <thead><tr><th>Scope</th><th>What it allows</th></tr></thead>
                <tbody>
                @foreach ($scopes as $scope)
                    <tr>
                        <td><code>{{ $scope->id }}</code></td>
                        <td>{{ $scope->description }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p class="muted small">No scopes requested. The client will be able to connect but not call any tool.</p>
        @endif

        <p class="muted small" style="margin-top:14px">
            Every tool call made with this authorization is recorded in the audit log with your user id.
            You can revoke access at any time by deleting the token.
        </p>

        <div class="actions">
            <form method="POST" action="{{ route('passport.authorizations.approve') }}">
                @csrf
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button class="primary" type="submit">Authorize</button>
            </form>

            <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button class="ghost" type="submit">Cancel</button>
            </form>
        </div>
    </div>
@endsection
