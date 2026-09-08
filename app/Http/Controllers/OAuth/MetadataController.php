<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * OAuth discovery documents. MCP clients fetch these before they hold any
 * token, so both are public. The protected-resource document (RFC 9728) is
 * what the WWW-Authenticate header on a 401 points at; the authorization
 * server document (RFC 8414) tells the client where to register, authorize
 * and exchange codes.
 */
final class MetadataController extends Controller
{
    public function protectedResource(): JsonResponse
    {
        return $this->respond([
            'resource' => url('/mcp'),
            'authorization_servers' => [$this->issuer()],
            'scopes_supported' => array_keys(config('mcp.scopes')),
            'bearer_methods_supported' => ['header'],
            'resource_name' => config('mcp.server.title'),
            'resource_documentation' => url('/'),
        ]);
    }

    public function authorizationServer(): JsonResponse
    {
        $issuer = $this->issuer();

        $document = [
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer.'/oauth/authorize',
            'token_endpoint' => $issuer.'/oauth/token',
            'scopes_supported' => array_keys(config('mcp.scopes')),
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post'],
            'code_challenge_methods_supported' => ['S256'],
            'service_documentation' => url('/'),
        ];

        if (config('mcp.oauth.dynamic_registration')) {
            $document['registration_endpoint'] = $issuer.'/oauth/register';
        }

        return $this->respond($document);
    }

    private function issuer(): string
    {
        return rtrim(url('/'), '/');
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function respond(array $document): JsonResponse
    {
        return response()->json($document, 200, [
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
