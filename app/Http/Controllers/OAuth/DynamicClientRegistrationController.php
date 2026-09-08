<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Passport\ClientRepository;

/**
 * RFC 7591 dynamic client registration.
 *
 * Passport ships no registration endpoint, and MCP clients expect one: Claude.ai
 * and MCP Inspector call it before starting the authorization-code flow. We only
 * ever create public clients (no secret), which forces PKCE on every login.
 */
final class DynamicClientRegistrationController extends Controller
{
    public function __invoke(Request $request, ClientRepository $clients): JsonResponse
    {
        if (! config('mcp.oauth.dynamic_registration')) {
            return $this->error('invalid_request', 'Dynamic client registration is disabled on this server.', 403);
        }

        $validator = Validator::make($request->all(), [
            'client_name' => ['required', 'string', 'max:120'],
            'redirect_uris' => ['required', 'array', 'min:1', 'max:10'],
            'redirect_uris.*' => ['required', 'string', 'max:2000'],
            'grant_types' => ['sometimes', 'array'],
            'grant_types.*' => ['string', 'in:authorization_code,refresh_token'],
            'response_types' => ['sometimes', 'array'],
            'response_types.*' => ['string', 'in:code'],
            'token_endpoint_auth_method' => ['sometimes', 'string', 'in:none'],
        ]);

        if ($validator->fails()) {
            $first = $validator->errors()->first();
            $code = str_contains($first, 'redirect_uris') ? 'invalid_redirect_uri' : 'invalid_client_metadata';

            return $this->error($code, $first, 400);
        }

        $data = $validator->validated();

        foreach ($data['redirect_uris'] as $uri) {
            if (($problem = $this->redirectUriProblem($uri)) !== null) {
                return $this->error('invalid_redirect_uri', $problem, 400);
            }
        }

        $client = $clients->createAuthorizationCodeGrantClient(
            name: $data['client_name'],
            redirectUris: array_values(array_unique($data['redirect_uris'])),
            confidential: false,
        );

        return response()->json([
            'client_id' => $client->getKey(),
            'client_name' => $client->name,
            'redirect_uris' => $client->redirect_uris,
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'client_id_issued_at' => $client->created_at?->getTimestamp(),
        ], 201);
    }

    /**
     * https only, except http on localhost for local MCP clients. Optionally
     * restricted to an allow-list of hosts from config.
     */
    private function redirectUriProblem(string $uri): ?string
    {
        $parts = parse_url($uri);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return "Redirect URI [{$uri}] is not an absolute URL.";
        }

        if (! empty($parts['fragment'])) {
            return "Redirect URI [{$uri}] must not contain a fragment.";
        }

        $host = strtolower($parts['host']);
        $isLoopback = in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);

        if ($parts['scheme'] !== 'https' && ! ($parts['scheme'] === 'http' && $isLoopback)) {
            return "Redirect URI [{$uri}] must use https (http is allowed only for localhost).";
        }

        $allowed = config('mcp.oauth.allowed_redirect_hosts', []);

        if ($allowed !== [] && ! in_array($host, array_map('strtolower', $allowed), true) && ! $isLoopback) {
            return "Redirect host [{$host}] is not on this server's allow-list.";
        }

        return null;
    }

    private function error(string $code, string $description, int $status): JsonResponse
    {
        return response()->json(['error' => $code, 'error_description' => $description], $status);
    }
}
