<?php

namespace Tests\Feature\Mcp;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProtocolTest extends TestCase
{
    use RefreshDatabase;

    public function test_initialize_returns_server_info_and_capabilities(): void
    {
        $user = User::factory()->create();

        $this->mcp([
            'jsonrpc' => '2.0',
            'id' => 'init-1',
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 'test', 'version' => '0']],
        ], $user)
            ->assertOk()
            ->assertHeader('MCP-Protocol-Version', '2025-06-18')
            ->assertJsonPath('id', 'init-1')
            ->assertJsonPath('result.protocolVersion', '2025-06-18')
            ->assertJsonPath('result.capabilities.tools.listChanged', false)
            ->assertJsonPath('result.serverInfo.name', config('mcp.server.name'));
    }

    public function test_initialize_falls_back_to_newest_version_for_unknown_request(): void
    {
        $user = User::factory()->create();

        $this->mcp(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '1999-01-01']], $user)
            ->assertOk()
            ->assertJsonPath('result.protocolVersion', config('mcp.server.protocol_version'));
    }

    public function test_ping_returns_empty_object(): void
    {
        $user = User::factory()->create();

        $response = $this->mcp(['jsonrpc' => '2.0', 'id' => 7, 'method' => 'ping'], $user)->assertOk();

        // JSON-RPC requires an object here, not a list; check the raw body.
        $this->assertStringContainsString('"result":{}', $response->getContent());
    }

    public function test_notifications_are_accepted_without_a_body(): void
    {
        $user = User::factory()->create();

        $this->mcp(['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $user)
            ->assertStatus(202);
    }

    public function test_batch_requests_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->mcp([['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']], $user)
            ->assertStatus(400)
            ->assertJsonPath('error.code', -32600);
    }

    public function test_unknown_method_is_a_json_rpc_error(): void
    {
        $user = User::factory()->create();

        $this->mcp(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'resources/list'], $user)
            ->assertOk()
            ->assertJsonPath('id', 2)
            ->assertJsonPath('error.code', -32601);
    }

    public function test_malformed_json_is_a_parse_error(): void
    {
        $user = User::factory()->create();
        $this->mcp([], $user);

        $this->call('POST', '/mcp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{not json')
            ->assertStatus(400)
            ->assertJsonPath('error.code', -32700);
    }

    public function test_tools_list_only_includes_tools_the_token_may_call(): void
    {
        $user = User::factory()->create();

        $names = $this->mcp(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], $user, ['read:jobs'])
            ->assertOk()
            ->json('result.tools.*.name');

        $this->assertEqualsCanonicalizing(['list_jobs', 'get_job'], $names);
    }

    public function test_tools_list_with_all_read_scopes_hides_write_tools_by_default(): void
    {
        $user = User::factory()->create();

        $names = $this->mcp(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], $user, ['read:jobs', 'read:revenue', 'write:jobs'])
            ->json('result.tools.*.name');

        $this->assertEqualsCanonicalizing(['list_jobs', 'get_job', 'revenue_summary'], $names);
        $this->assertNotContains('update_job_status', $names);
    }

    public function test_tools_list_shows_write_tools_when_writes_enabled_and_scope_held(): void
    {
        config(['mcp.writes_enabled' => true]);
        $user = User::factory()->create();

        $names = $this->mcp(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], $user, ['write:jobs'])
            ->json('result.tools.*.name');

        $this->assertSame(['update_job_status'], $names);
    }

    public function test_tool_definitions_carry_schema_scope_and_annotations(): void
    {
        $user = User::factory()->create();

        $tool = collect($this->mcp(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], $user)->json('result.tools'))
            ->firstWhere('name', 'list_jobs');

        $this->assertSame('object', $tool['inputSchema']['type']);
        $this->assertFalse($tool['inputSchema']['additionalProperties']);
        $this->assertTrue($tool['annotations']['readOnlyHint']);
        $this->assertFalse($tool['annotations']['destructiveHint']);
    }
}
