<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class McpCreateToken extends Command
{
    /**
     * @var string
     */
    protected $signature = 'mcp:create-token
                            {email? : If omitted, uses the first user (by id)}';

    /**
     * @var string
     */
    protected $description = 'Create a Sanctum personal access token for MCP (paste into BLACKWIDOW_API_TOKEN)';

    public function handle(): int
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $this->error('Database is not reachable. Fix .env and run migrations, then try again.');

            return self::FAILURE;
        }

        $email = $this->argument('email');
        $user = $email
            ? User::query()->where('email', $email)->firstOrFail()
            : User::query()->orderBy('id')->firstOrFail();

        $name = 'mcp-'.now()->format('Y-m-d_His');
        $token = $user->createToken($name);

        $this->info('User: '.$user->email);
        $this->line('Token (only shown once — add to BLACKWIDOW_API_TOKEN in Claude / Cursor MCP env):');
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
