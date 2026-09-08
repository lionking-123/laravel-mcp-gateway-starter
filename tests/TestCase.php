<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Passport's token guard needs a key pair to exist even when tests
        // authenticate through Passport::actingAs(). Generate once per machine.
        if (! file_exists(Passport::keyPath('oauth-private.key'))) {
            $this->artisan('passport:keys', ['--force' => true]);
        }
    }

    /**
     * Send one JSON-RPC message to the MCP endpoint as the given user.
     *
     * @param  array<string, mixed>  $message
     * @param  list<string>  $scopes
     */
    protected function mcp(array $message, ?User $user = null, array $scopes = ['read:jobs', 'read:revenue']): TestResponse
    {
        if ($user !== null) {
            Passport::actingAs($user, $scopes);
        }

        return $this->postJson('/mcp', $message, [
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => '2025-06-18',
        ]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $scopes
     */
    protected function callTool(string $name, array $arguments, User $user, array $scopes = ['read:jobs', 'read:revenue'], int $id = 1): TestResponse
    {
        return $this->mcp([
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments],
        ], $user, $scopes);
    }
}
