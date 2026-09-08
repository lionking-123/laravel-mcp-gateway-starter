@extends('layouts.app')

@section('content')
    <h1>{{ config('mcp.server.title') }}</h1>
    <p class="lede">
        An OAuth-protected MCP server over a sample field-service dataset. AI assistants call the tools below;
        they never reach the database directly.
    </p>

    <div class="card">
        <table>
            <tbody>
            <tr><th style="width:190px">MCP endpoint</th><td><code>{{ $serverUrl }}</code> <span class="muted small">(Streamable HTTP, POST)</span></td></tr>
            <tr><th>Authorization</th><td>OAuth 2.0 authorization code + PKCE, dynamic client registration at <code>/oauth/register</code></td></tr>
            <tr><th>Discovery</th><td><a href="{{ route('oauth.metadata.resource') }}"><code>/.well-known/oauth-protected-resource</code></a> · <a href="{{ route('oauth.metadata.server') }}"><code>/.well-known/oauth-authorization-server</code></a></td></tr>
            <tr><th>Writes</th><td>
                @if ($writesEnabled)
                    <span class="pill warn">enabled</span> <span class="muted small">write tools are live for tokens with a write scope</span>
                @else
                    <span class="pill ok">disabled</span> <span class="muted small">write tools are hidden and refuse to run (MCP_WRITES_ENABLED=false)</span>
                @endif
            </td></tr>
            </tbody>
        </table>
    </div>

    <h2>Tools</h2>
    <div class="card">
        <table>
            <thead><tr><th>Tool</th><th>Scope</th><th>Description</th></tr></thead>
            <tbody>
            @foreach ($tools as $tool)
                <tr>
                    <td><code>{{ $tool->name() }}</code>
                        @if ($tool->isWrite())
                            <br><span class="pill {{ $writesEnabled ? 'warn' : 'bad' }}">write{{ $writesEnabled ? '' : ' · off' }}</span>
                        @else
                            <br><span class="pill ok">read-only</span>
                        @endif
                    </td>
                    <td><code>{{ $tool->scope() }}</code></td>
                    <td class="small">{{ $tool->description() }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <h2>Scopes</h2>
    <div class="card">
        <table>
            <tbody>
            @foreach ($scopes as $id => $description)
                <tr><td style="width:190px"><code>{{ $id }}</code></td><td class="small">{{ $description }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <h2>Connect a client</h2>
    <div class="card">
        <p class="small"><strong>Claude.ai</strong> (needs a public https URL): Settings → Connectors → Add custom connector → paste <code>{{ $serverUrl }}</code>. Claude registers itself, sends you to the sign-in page here, and asks for consent.</p>
        <p class="small"><strong>MCP Inspector</strong> (local): <code>npx @modelcontextprotocol/inspector</code>, transport <em>Streamable HTTP</em>, URL <code>{{ $serverUrl }}</code>, then <em>Open Auth Settings → Quick OAuth Flow</em>.</p>
        <p class="small"><strong>curl</strong>: <code>php artisan mcp:token demo@example.com</code> prints a bearer token.</p>
<pre>curl -s {{ $serverUrl }} \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"list_jobs","arguments":{"status":"scheduled","limit":5}}}'</pre>
    </div>

    @auth
        <h2>Recent audit entries</h2>
        <div class="card">
            @if ($recentAudit->isEmpty())
                <p class="muted small">No tool calls recorded yet.</p>
            @else
                <table>
                    <thead><tr><th>When</th><th>Tool</th><th>Status</th><th>User</th><th>Args</th><th>Size</th><th>ms</th></tr></thead>
                    <tbody>
                    @foreach ($recentAudit as $row)
                        <tr class="small">
                            <td>{{ $row->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td><code>{{ $row->tool }}</code></td>
                            <td><span class="pill {{ $row->status === 'success' ? 'ok' : 'bad' }}">{{ $row->status }}</span>{{ $row->error_code ? ' '.$row->error_code : '' }}</td>
                            <td>{{ $row->user_id ?? '–' }}</td>
                            <td><code>{{ \Illuminate\Support\Str::limit(json_encode($row->arguments), 60) }}</code></td>
                            <td>{{ number_format($row->result_bytes) }} B</td>
                            <td>{{ $row->duration_ms }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endauth
@endsection
