<?php

namespace Tests\Feature\OAuth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DiscoveryAndRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_resource_metadata_points_at_this_server(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource')
            ->assertOk()
            ->assertJsonPath('resource', url('/mcp'))
            ->assertJsonPath('authorization_servers.0', rtrim(url('/'), '/'))
            ->assertJsonPath('scopes_supported', array_keys(config('mcp.scopes')));

        $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertOk();
    }

    public function test_authorization_server_metadata_advertises_pkce_public_clients_and_registration(): void
    {
        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('authorization_endpoint', url('/oauth/authorize'))
            ->assertJsonPath('token_endpoint', url('/oauth/token'))
            ->assertJsonPath('registration_endpoint', url('/oauth/register'))
            ->assertJsonPath('code_challenge_methods_supported', ['S256'])
            ->assertJsonPath('grant_types_supported', ['authorization_code', 'refresh_token'])
            ->assertJsonFragment(['token_endpoint_auth_methods_supported' => ['none', 'client_secret_post']]);
    }

    public function test_registration_creates_a_public_client(): void
    {
        $response = $this->postJson('/oauth/register', [
            'client_name' => 'Claude',
            'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ]);

        $response->assertCreated()
            ->assertJsonPath('client_name', 'Claude')
            ->assertJsonPath('token_endpoint_auth_method', 'none')
            ->assertJsonPath('redirect_uris.0', 'https://claude.ai/api/mcp/auth_callback')
            ->assertJsonMissing(['client_secret']);

        $client = Passport::client()->newQuery()->findOrFail($response->json('client_id'));

        $this->assertNull($client->secret);
        $this->assertFalse($client->confidential());
        $this->assertTrue($client->hasGrantType('authorization_code'));
    }

    public function test_registration_allows_http_only_on_localhost(): void
    {
        $this->postJson('/oauth/register', [
            'client_name' => 'Inspector',
            'redirect_uris' => ['http://localhost:6274/oauth/callback'],
        ])->assertCreated();

        $this->postJson('/oauth/register', [
            'client_name' => 'Evil',
            'redirect_uris' => ['http://attacker.example/callback'],
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');
    }

    public function test_registration_honours_redirect_host_allow_list(): void
    {
        config(['mcp.oauth.allowed_redirect_hosts' => ['claude.ai']]);

        $this->postJson('/oauth/register', ['client_name' => 'Other', 'redirect_uris' => ['https://other.example/cb']])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_redirect_uri');

        $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback']])
            ->assertCreated();
    }

    public function test_registration_can_be_disabled(): void
    {
        config(['mcp.oauth.dynamic_registration' => false]);

        $this->postJson('/oauth/register', ['client_name' => 'X', 'redirect_uris' => ['https://x.example/cb']])
            ->assertStatus(403);

        $this->getJson('/.well-known/oauth-authorization-server')->assertJsonMissingPath('registration_endpoint');
    }

    public function test_authorize_endpoint_sends_anonymous_users_to_login_for_a_registered_client(): void
    {
        $clientId = $this->postJson('/oauth/register', [
            'client_name' => 'Claude',
            'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
        ])->json('client_id');

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'response_type' => 'code',
            'scope' => 'read:jobs read:revenue',
            'state' => 'abc123',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', 'verifier', true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);

        $this->get('/oauth/authorize?'.$query)->assertRedirect(route('login'));
    }

    public function test_authorize_endpoint_rejects_unknown_clients(): void
    {
        $this->get('/oauth/authorize?client_id=nope&redirect_uri=https%3A%2F%2Fx.example%2Fcb&response_type=code')
            ->assertStatus(401);
    }
}
