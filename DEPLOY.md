# Deploying

Everything a normal Laravel deployment needs, plus the parts that are specific
to running an OAuth-protected MCP server that a hosted assistant will call from
the public internet.

## Checklist

1. **HTTPS and a stable hostname.** MCP clients refuse plain http except on
   localhost, and OAuth redirect URIs are validated against the registered
   host. Set `APP_URL` to the exact public origin (scheme, host, port). The
   discovery documents and the `WWW-Authenticate` header are built from it.
2. **Trusted proxies.** Behind a load balancer, tunnel or Cloudflare, tell
   Laravel to trust forwarded headers so `url()` produces https URLs:
   `bootstrap/app.php` → `$middleware->trustProxies(at: '*')` (or the specific
   CIDRs of your proxy).
3. **Keys.** Run `php artisan passport:keys` once per environment, or set
   `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` from your secret store. Never
   commit `storage/oauth-*.key`.
4. **Cache store.** Rate limiting uses the cache. `database` works; Redis is
   better under load. Do not use `array` outside tests.
5. **Writes off.** Confirm `MCP_WRITES_ENABLED=false` in production until you
   have deliberately decided otherwise.
6. **Real authentication.** Replace `App\Http\Controllers\Auth\LoginController`
   with your SSO or existing login. Passport only needs a session-authenticated
   user when it reaches `/oauth/authorize`.
7. **Redirect allow-list.** Set `MCP_ALLOWED_REDIRECT_HOSTS=claude.ai` (comma
   separated) so only the clients you expect can register.
8. **Scheduler.** Run `php artisan schedule:run` every minute so the audit log
   is pruned.
9. **Health.** `/up` is unauthenticated and returns 200.

## Quick public URL for testing with Claude.ai

Claude.ai connectors need a public https URL. For a demo, a tunnel is enough:

```bash
cloudflared tunnel --url http://localhost:8000
```

Copy the `https://<random>.trycloudflare.com` origin into `APP_URL`, run
`php artisan config:clear`, then in Claude.ai open Settings → Connectors →
Add custom connector and paste `https://<random>.trycloudflare.com/mcp`.
Claude fetches `/.well-known/oauth-protected-resource`, registers itself at
`/oauth/register`, sends you to `/login`, shows the consent screen, and starts
calling tools. `ngrok http 8000` works the same way.

## Cloudflare in front of the origin

Putting Cloudflare in front is a good idea: it terminates TLS, hides the origin
and gives you a WAF. Two things bite MCP traffic specifically.

### 1. WAF and bot rules block the OAuth handshake

MCP clients are not browsers. Claude's OAuth discovery and token requests arrive
with a non-browser user agent, no cookies, JSON bodies, and (for `/oauth/token`)
form-encoded POSTs. Bot Fight Mode, Browser Integrity Check and some managed
WAF rules classify that as automation and answer with a 403 challenge page.
From the client's side the connector "fails to connect" with no useful error.

Fix it with a **WAF custom rule** that skips the bot and managed rules for the
gateway paths only:

```
(http.request.uri.path eq "/mcp")
or (http.request.uri.path wildcard "/oauth/*")
or (http.request.uri.path wildcard "/.well-known/*")
```

Action: *Skip* → All remaining custom rules, Rate limiting rules, Managed
rules, Bot Fight Mode / Super Bot Fight Mode. Keep everything else on for the
rest of the site. If you want to go further, add an IP allow-list for the
assistant's egress ranges (Anthropic publishes theirs) as a second rule, and
leave the Laravel-side controls on regardless: the WAF is layer 1, not the
only layer.

Also turn off Rocket Loader, email obfuscation and any HTML-rewriting features
for these paths; they must never rewrite the JSON discovery documents.

### 2. The origin cannot (or should not) host OAuth itself

Sometimes the application behind the gateway cannot be reached from the public
internet, sits behind Cloudflare Access, or you do not want to expose its
login page to the world. The pattern then is an **OAuth-terminating Worker**:

```
Claude ──OAuth (PKCE)──▶ Cloudflare Worker ──service token──▶ Laravel /mcp
                          (authorization server,              (resource server,
                           consent UI, token store)            validates the Worker's
                                                               token, runs tools)
```

- The Worker implements the discovery documents, `/oauth/register`,
  `/oauth/authorize` and `/oauth/token`, typically with Cloudflare's
  `@cloudflare/workers-oauth-provider` library and KV for token storage. The
  human authenticates against *your* identity provider inside the Worker.
- On each `/mcp` call the Worker validates the client's access token, then
  forwards the JSON-RPC body upstream with a fixed service token (or mTLS)
  and an `X-Forwarded-User` header carrying the identity it verified.
- Laravel keeps the tool layer, scopes, audit log and rate limits from this
  repository; its `/mcp` route simply trusts the Worker's token and takes the
  user identity from the header. Passport's OAuth routes stay closed to the
  internet.

Trade-offs: you now run two authorization systems and must keep scope names in
sync, and the audit log records the Worker as the OAuth client with the user
taken from a header rather than a first-party token. In exchange, the origin's
login page is never public and Cloudflare's edge does the heavy lifting.

Use this when the origin is private or the client's IT policy requires it.
When the Laravel app is already public and has decent login and MFA, the
simpler path is to let Passport be the authorization server, as this starter
does, and fix the WAF rules as described above.

## Operating

- **Revoking a client:** delete its row in `oauth_clients` (or set `revoked`),
  and its tokens stop working immediately.
- **Revoking one user's access:** revoke their tokens in `oauth_access_tokens`
  and `oauth_refresh_tokens`.
- **Rotating keys:** issue new keys with `passport:keys --force`; every access
  token becomes invalid and clients re-authorize on the next call.
- **Reading the audit log:** it is a table. `SELECT tool, status, COUNT(*) FROM
  mcp_audit_logs GROUP BY 1, 2` tells you what the assistant is actually being
  asked. Sign in at `/` to see the last few entries.
- **Turning writes on:** set `MCP_WRITES_ENABLED=true`, re-issue tokens with a
  `write:` scope (existing tokens keep their old scopes), and tell users the
  assistant will now ask for approval before changing data.
