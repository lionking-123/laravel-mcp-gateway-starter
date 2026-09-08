<?php

use App\Http\Middleware\McpRateLimit;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Stateless routes: no session, no CSRF. Each route declares its own middleware.
            Route::group([], base_path('routes/mcp.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'mcp.ratelimit' => McpRateLimit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('mcp*')
                || $request->is('oauth/register')
                || $request->is('.well-known/*')
                || $request->expectsJson();
        });

        // A missing or invalid bearer token on the MCP endpoint must answer with
        // WWW-Authenticate pointing at the resource metadata. That header is how
        // MCP clients discover they need to run the OAuth flow.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('mcp*')) {
                return null;
            }

            $metadata = url('/.well-known/oauth-protected-resource');

            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => [
                    'code' => -32001,
                    'message' => 'Unauthorized. Obtain an OAuth 2.0 access token and send it as a Bearer token.',
                    'data' => ['resource_metadata' => $metadata],
                ],
            ], 401, [
                'WWW-Authenticate' => sprintf(
                    'Bearer realm="%s", error="invalid_token", resource_metadata="%s"',
                    config('mcp.server.name'),
                    $metadata,
                ),
            ]);
        });
    })->create();
