<?php

use App\Http\Controllers\Mcp\McpController;
use App\Http\Controllers\OAuth\DynamicClientRegistrationController;
use App\Http\Controllers\OAuth\MetadataController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MCP and OAuth discovery routes
|--------------------------------------------------------------------------
|
| Everything an MCP client touches. None of these routes use the "web"
| middleware group: no sessions, no CSRF. CORS for these paths is configured
| in config/cors.php. Passport registers /oauth/authorize and /oauth/token.
|
*/

// RFC 9728: tells clients which authorization server protects /mcp.
Route::get('/.well-known/oauth-protected-resource', [MetadataController::class, 'protectedResource'])
    ->name('oauth.metadata.resource');
Route::get('/.well-known/oauth-protected-resource/mcp', [MetadataController::class, 'protectedResource']);

// RFC 8414: where to register, authorize and exchange codes.
Route::get('/.well-known/oauth-authorization-server', [MetadataController::class, 'authorizationServer'])
    ->name('oauth.metadata.server');

// RFC 7591: MCP clients register themselves as public PKCE clients.
Route::post('/oauth/register', DynamicClientRegistrationController::class)
    ->middleware('throttle:oauth-register')
    ->name('oauth.register');

// MCP Streamable HTTP. POST carries JSON-RPC; GET (server-initiated stream) is
// declined with 405; DELETE ends a session (a no-op here, the server is stateless).
Route::match(['get', 'post', 'delete'], '/mcp', McpController::class)
    ->middleware(['auth:api', 'mcp.ratelimit'])
    ->name('mcp');
