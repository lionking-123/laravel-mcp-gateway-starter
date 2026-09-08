<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-token limits with two windows (per minute and per day). Keyed on the
 * access token, so one runaway client cannot exhaust another's budget.
 * Runs after auth:api, so an unauthenticated request never reaches it.
 */
final class McpRateLimit
{
    public function __construct(private readonly RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user('api')?->token();
        $subject = $token?->oauth_access_token_id ?? 'ip:'.$request->ip();

        $windows = [
            ['key' => "mcp:minute:{$subject}", 'max' => (int) config('mcp.rate_limit.per_minute'), 'seconds' => 60],
            ['key' => "mcp:day:{$subject}", 'max' => (int) config('mcp.rate_limit.per_day'), 'seconds' => 86400],
        ];

        foreach ($windows as $window) {
            if ($window['max'] > 0 && $this->limiter->tooManyAttempts($window['key'], $window['max'])) {
                $retryAfter = $this->limiter->availableIn($window['key']);

                return response()->json([
                    'jsonrpc' => '2.0',
                    'id' => null,
                    'error' => [
                        'code' => -32000,
                        'message' => "Rate limit exceeded for this token. Retry in {$retryAfter} seconds.",
                        'data' => ['retry_after_seconds' => $retryAfter],
                    ],
                ], 429, ['Retry-After' => (string) $retryAfter]);
            }
        }

        foreach ($windows as $window) {
            if ($window['max'] > 0) {
                $this->limiter->hit($window['key'], $window['seconds']);
            }
        }

        $response = $next($request);

        $minute = $windows[0];
        $response->headers->set('X-RateLimit-Limit', (string) $minute['max']);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $minute['max'] - $this->limiter->attempts($minute['key'])));

        return $response;
    }
}
