<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Mcp\Exceptions\JsonRpcException;
use App\Mcp\McpServer;
use App\Mcp\ToolContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * MCP Streamable HTTP transport, stateless variant.
 *
 * POST   one JSON-RPC message in, one JSON response out (or 202 for notifications)
 * GET    would open a server-initiated SSE stream; this server has nothing to
 *        push, so it declines with 405 as the specification allows
 * DELETE ends a session; there are no sessions here, so it is a no-op
 *
 * Authentication happens before this controller (auth:api). By the time we
 * run, the request carries a valid Passport access token.
 */
final class McpController extends Controller
{
    public function __invoke(Request $request, McpServer $server): Response
    {
        if ($request->isMethod('GET')) {
            return $this->json([
                'error' => 'This server does not open server-initiated streams. Send JSON-RPC 2.0 requests with POST.',
            ], 405, ['Allow' => 'POST, DELETE']);
        }

        if ($request->isMethod('DELETE')) {
            return response()->noContent();
        }

        $decoded = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return $this->rpcError(null, JsonRpcException::PARSE_ERROR, 'Parse error: the body must be a single JSON-RPC 2.0 object.', 400);
        }

        if (array_is_list($decoded)) {
            return $this->rpcError(null, JsonRpcException::INVALID_REQUEST, 'Batch requests are not supported. Send one JSON-RPC message per HTTP request.', 400);
        }

        try {
            $response = $server->handle($decoded, $this->context($request));
        } catch (JsonRpcException $e) {
            return $this->rpcError($decoded['id'] ?? null, $e->getCode(), $e->getMessage(), 400, $e->data);
        }

        if ($response === null) {
            return response()->noContent(202)->withHeaders($this->headers());
        }

        return $this->json($response, 200);
    }

    private function context(Request $request): ToolContext
    {
        $user = $request->user('api');
        $token = $user?->token();

        return new ToolContext(
            user: $user,
            scopes: array_values((array) ($token?->oauth_scopes ?? [])),
            tokenId: $token?->oauth_access_token_id ?? null,
            clientId: $token?->oauth_client_id ?? null,
            requestId: Str::limit((string) ($request->header('X-Request-Id') ?: Str::uuid()), 64, ''),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $extraHeaders
     */
    private function json(array $payload, int $status, array $extraHeaders = []): JsonResponse
    {
        return response()->json($payload, $status, $this->headers() + $extraHeaders);
    }

    private function rpcError(mixed $id, int $code, string $message, int $status, mixed $data = null): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];

        if ($data !== null) {
            $error['data'] = $data;
        }

        return $this->json(['jsonrpc' => '2.0', 'id' => $id, 'error' => $error], $status);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['MCP-Protocol-Version' => (string) config('mcp.server.protocol_version')];
    }
}
