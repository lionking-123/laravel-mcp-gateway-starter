<?php

namespace App\Providers;

use App\Mcp\ToolRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One registry for the whole request; tools come from config/mcp.php only.
        $this->app->singleton(ToolRegistry::class, function ($app) {
            return new ToolRegistry(array_map(
                fn (string $class) => $app->make($class),
                config('mcp.tools', []),
            ));
        });
    }

    public function boot(): void
    {
        // Scopes the authorization server knows about. Tools may only use these.
        Passport::tokensCan(config('mcp.scopes'));

        // Short-lived access tokens, longer refresh tokens. MCP clients refresh silently.
        Passport::tokensExpireIn(now()->addMinutes((int) config('mcp.tokens.access_ttl_minutes')));
        Passport::refreshTokensExpireIn(now()->addDays((int) config('mcp.tokens.refresh_ttl_days')));
        Passport::personalAccessTokensExpireIn(now()->addDays((int) config('mcp.tokens.refresh_ttl_days')));

        // The consent screen shown during the authorization-code flow.
        Passport::authorizationView('oauth.authorize');

        RateLimiter::for('oauth-register', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
    }
}
