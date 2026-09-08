<?php

namespace App\Console\Commands;

use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

/**
 * Everything between "composer install" and a working server, in one command.
 */
final class McpSetupCommand extends Command
{
    protected $signature = 'mcp:setup {--fresh : Drop every table and start over}';

    protected $description = 'Migrate, generate OAuth keys, seed demo data, and create the local personal-access client';

    public function handle(ClientRepository $clients): int
    {
        $database = config('database.connections.'.config('database.default').'.database');

        if (config('database.default') === 'sqlite' && $database !== ':memory:' && ! file_exists($database)) {
            touch($database);
            $this->components->info("Created SQLite database at {$database}");
        }

        $this->call($this->option('fresh') ? 'migrate:fresh' : 'migrate', ['--force' => true]);

        if (! file_exists(Passport::keyPath('oauth-private.key'))) {
            $this->call('passport:keys');
        } else {
            $this->components->info('OAuth keys already exist.');
        }

        $this->call('db:seed', ['--class' => DemoDataSeeder::class, '--force' => true]);

        $hasPersonalClient = Passport::client()->newQuery()
            ->where('grant_types', 'like', '%personal_access%')
            ->exists();

        if (! $hasPersonalClient) {
            $clients->createPersonalAccessGrantClient('Local CLI', 'users');
            $this->components->info('Created the personal-access client used by "php artisan mcp:token".');
        }

        $this->newLine();
        $this->components->info('Ready.');
        $this->line('  Demo login:   '.DemoDataSeeder::DEMO_EMAIL.' / '.DemoDataSeeder::DEMO_PASSWORD);
        $this->line('  Start:        php artisan serve');
        $this->line('  MCP endpoint: '.url('/mcp'));
        $this->line('  CLI token:    php artisan mcp:token '.DemoDataSeeder::DEMO_EMAIL);

        return self::SUCCESS;
    }
}
