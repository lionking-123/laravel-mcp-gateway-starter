<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Mint a personal access token for curl or scripted testing. Interactive MCP
 * clients should use the OAuth flow instead; this exists so the quickstart
 * can prove the server works in one terminal.
 */
final class McpTokenCommand extends Command
{
    protected $signature = 'mcp:token
        {email : The user the token acts as}
        {--scopes=read:jobs,read:revenue : Comma-separated scopes}
        {--name=cli : A label for the token}';

    protected $description = 'Create a personal access token for testing the MCP endpoint';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->components->error("No user with email {$this->argument('email')}. Run php artisan mcp:setup first.");

            return self::FAILURE;
        }

        $scopes = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('scopes')))));
        $unknown = array_diff($scopes, array_keys(config('mcp.scopes')));

        if ($unknown !== []) {
            $this->components->error('Unknown scope(s): '.implode(', ', $unknown).'. Known: '.implode(', ', array_keys(config('mcp.scopes'))));

            return self::FAILURE;
        }

        $token = $user->createToken((string) $this->option('name'), $scopes);

        $this->components->info('Token created for '.$user->email.' with scopes: '.implode(', ', $scopes));
        $this->line('Store it now; it is not shown again.');
        $this->newLine();
        $this->line($token->accessToken);
        $this->newLine();
        $this->line('Try it:');
        $this->line('  curl -s '.url('/mcp').' -H "Authorization: Bearer <token>" -H "Content-Type: application/json" \\');
        $this->line('    -d \'{"jsonrpc":"2.0","id":1,"method":"tools/list"}\'');

        return self::SUCCESS;
    }
}
