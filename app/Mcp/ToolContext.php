<?php

namespace App\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who is calling, with what token, under which request.
 * Built once per HTTP request and handed to every tool.
 */
final class ToolContext
{
    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        public readonly ?Authenticatable $user,
        public readonly array $scopes,
        public readonly ?string $tokenId,
        public readonly ?string $clientId,
        public readonly string $requestId,
    ) {}

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true) || in_array('*', $this->scopes, true);
    }

    public function userId(): int|string|null
    {
        return $this->user?->getAuthIdentifier();
    }
}
