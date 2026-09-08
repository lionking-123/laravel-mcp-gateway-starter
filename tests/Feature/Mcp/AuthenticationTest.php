<?php

namespace Tests\Feature\Mcp;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_without_token_is_rejected_with_resource_metadata_hint(): void
    {
        $response = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

        $response->assertStatus(401)
            ->assertJsonPath('jsonrpc', '2.0')
            ->assertJsonPath('error.code', -32001)
            ->assertJsonPath('error.data.resource_metadata', url('/.well-known/oauth-protected-resource'));

        $this->assertStringContainsString(
            'resource_metadata="'.url('/.well-known/oauth-protected-resource').'"',
            $response->headers->get('WWW-Authenticate'),
        );
    }

    public function test_garbage_bearer_token_is_rejected(): void
    {
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'], [
            'Authorization' => 'Bearer not-a-real-token',
        ])->assertStatus(401);
    }

    public function test_get_declines_server_initiated_stream(): void
    {
        $user = User::factory()->create();

        $this->mcp([], $user); // authenticates the guard for this request cycle

        $this->getJson('/mcp')
            ->assertStatus(405)
            ->assertHeader('Allow', 'POST, DELETE');
    }

    public function test_delete_ends_session_as_no_op(): void
    {
        $user = User::factory()->create();
        $this->mcp([], $user);

        $this->deleteJson('/mcp')->assertNoContent();
    }
}
