# Guardrails

This document explains the security design of the gateway: what the model is
allowed to do, what it is never allowed to do, and where each rule lives in the
code. Read it before adding a tool. Hand it to a client's IT lead when they ask
"what exactly can the AI see?"

## The one rule everything else follows from

**The model never touches the database.** It calls tools. A tool is a PHP class
with a fixed name, a fixed input schema, a fixed scope, and a `handle()` method
that runs a query *you* wrote. There is no "run this SQL" tool, no "fetch this
URL" tool, no generic ORM passthrough, and there never should be. If the model
needs a new question answered, a developer adds a tool and it goes through code
review like any other change.

## Layers

A request has to pass all of these, in order. Each is independent of the others.

| # | Control | Where | What it stops |
|---|---|---|---|
| 1 | TLS and edge rules | Your host / Cloudflare (see DEPLOY.md) | Traffic that should never reach PHP |
| 2 | OAuth 2.0 bearer token | `auth:api` guard, Passport | Anyone without a valid, unexpired token |
| 3 | Per-token rate limit | `App\Http\Middleware\McpRateLimit` | Runaway clients and brute force |
| 4 | Tool catalogue | `config/mcp.php` → `ToolRegistry` | Calling anything that is not on the list |
| 5 | Writes switch | `mcp.writes_enabled` | Every write tool, until an operator flips it |
| 6 | Scope check | `McpServer::callTool` | A token using a tool outside its scopes |
| 7 | Input schema | `SchemaValidator` | Unknown arguments, wrong types, out-of-range values |
| 8 | Presenters (field allow-lists) | `App\Mcp\Presenters\*` | Leaking columns the model has no business seeing |
| 9 | Result size cap | `mcp.limits.max_result_bytes` | Dumping a table through a wide query |
| 10 | Audit log | `App\Mcp\Audit\AuditLogger` | Not knowing what happened |

## Read-only by default

- Scopes starting with `read:` can only reach tools whose `handle()` performs
  SELECTs. This is a convention enforced by review, so keep write logic out of
  read tools even when it would be convenient.
- Scopes starting with `write:` mark tools that change data. `Tool::isWrite()`
  derives this from the scope, and the registry hides those tools from
  `tools/list` and refuses `tools/call` while `MCP_WRITES_ENABLED` is false.
  That flag is read from the environment, not from a request, so no client can
  turn it on.
- The sample write tool (`update_job_status`) also shows the pattern for the
  day you do enable writes: a narrow action, a legal-transition table, a
  required `confirm: true` argument that clients should only send after a human
  approved the exact change, and a `reason` that lands in the audit log.

## Allow-listed fields

Every row that leaves the gateway goes through a presenter. The presenters are
the complete list of what the model can see:

- `CustomerPresenter::public()` returns id, name, city. Email, phone and
  internal notes are on the model and are never returned.
- `JobPresenter` returns job fields plus the public customer shape. Internal
  notes are never returned.
- `InvoicePresenter` returns number, status, amount and dates.

To expose a new field, add it to the presenter. Do not return `$model->toArray()`
from a tool; that is how a column added next year ends up in a chat window.

## No raw query passthrough

Tool inputs are typed values (an enum, a date, an id, a bounded integer), never
fragments of a query. `list_jobs` accepts `status` as an enum and `search` as a
short string that is bound as a parameter with LIKE wildcards escaped. If you
find yourself accepting a "filter expression" or "field list" from the model,
stop and add a specific tool instead.

## Scopes are the permission model

Scopes are declared once, in `config/mcp.php`, and given to Passport with
`Passport::tokensCan()`. A tool must declare a scope that exists there or the
registry refuses to boot. Tokens get scopes at authorization time; the consent
screen shows the user what each scope allows in plain words. Keep scopes coarse
enough to explain on one line and fine enough that a "revenue reader" cannot
list jobs.

`tools/list` only returns tools the token may call. The model never sees a tool
it cannot use, which removes a whole class of "I tried but was denied" loops.

## Human approval for writes

When writes are enabled, the guardrail moves from the server to the client
workflow, and the tool description is where you make that workflow explicit:

1. The description tells the model to show the user the proposed change and get
   explicit approval first.
2. The schema requires `confirm: true`, so the model cannot call the tool with
   a default. It has to decide to set it.
3. `destructiveHint: true` in the annotations lets well-behaved clients (Claude,
   MCP Inspector) prompt the human before the call is sent.
4. The audit row records who, what, when, and the `reason`.

None of this is cryptographic proof that a human approved. It is defence in
depth around a tool that is, by design, narrow and reversible. Do not expose
irreversible writes (delete, send, pay) through an assistant.

## Audit log

Every `tools/call` attempt writes one row to `mcp_audit_logs`, including calls
that were refused (`denied_scope`, `denied_disabled`, `invalid_input`,
`unknown_tool`). The row carries the user id, OAuth client id, token id, tool
name, arguments, outcome, result size in bytes, duration, IP and a request id
(echoed from `X-Request-Id` when the client sends one).

The logger is fail-closed: if the row cannot be written, the call fails. A
gateway that cannot account for an answer should not give it.

Set `MCP_AUDIT_LOG_ARGUMENTS=false` if tool inputs may contain personal data
you are not allowed to retain. Prune with `php artisan mcp:audit:prune`, which
is scheduled daily in `routes/console.php`.

## Rate limits

Limits are per access token, in two windows (per minute and per day), so one
noisy client cannot starve another. Responses carry `X-RateLimit-Limit` and
`X-RateLimit-Remaining`; a 429 carries `Retry-After` and a JSON-RPC error the
model can read. Registration and login have their own IP-based throttles.

## Tokens

- Access tokens are short-lived (60 minutes by default); refresh tokens are
  longer (30 days). MCP clients refresh silently.
- Dynamic registration creates **public** clients only. There is no client
  secret to leak, and public clients are forced to use PKCE (S256).
- Redirect URIs must be https, except http on localhost for local tools. You
  can further restrict hosts with `MCP_ALLOWED_REDIRECT_HOSTS`.
- Personal access tokens (`php artisan mcp:token`) exist for curl and scripts.
  Treat them like passwords.

## What this starter does not protect against

- **Prompt injection through data.** If a customer name contains instructions,
  the model will read them. Keep tools returning the minimum, prefer structured
  fields over free text, and treat write tools with extra suspicion.
- **A compromised user account.** The gateway is exactly as trustworthy as the
  login in front of `/oauth/authorize`. Use your real authentication (SSO, MFA),
  not the demo login controller.
- **Inference from aggregates.** `revenue_summary` returns totals, but a
  narrow enough filter is a single record. Think about minimum group sizes if
  that matters in your domain.

## Adding a tool: the checklist

1. Does an existing tool already answer this? Extend its schema first.
2. Pick the scope. If none fits, add one to `config/mcp.php` with a one-line
   description a non-technical user will understand on the consent screen.
3. Write the input schema with `additionalProperties: false` and bounds on
   every string and integer.
4. Route every returned row through a presenter. Add fields deliberately.
5. Cap the result: `limit` with a maximum, or an aggregate.
6. Write the description for the model: what it returns, what it does not,
   units, and when to use another tool instead.
7. Add a feature test that proves a sensitive column is absent from the output.
8. Register the class in `config/mcp.php`.
