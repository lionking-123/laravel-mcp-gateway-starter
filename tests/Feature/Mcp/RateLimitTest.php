<?php

namespace Tests\Feature\Mcp;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_beyond_the_per_minute_limit_get_429_with_retry_after(): void
    {
        config(['mcp.rate_limit.per_minute' => 2]);
        $user = User::factory()->create();

        $this->mcp(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'], $user)->assertOk()
            ->assertHeader('X-RateLimit-Remaining', '1');
        $this->mcp(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'ping'], $user)->assertOk()
            ->assertHeader('X-RateLimit-Remaining', '0');

        $this->mcp(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'ping'], $user)
            ->assertStatus(429)
            ->assertJsonPath('error.code', -32000)
            ->assertHeader('Retry-After');
    }
}
