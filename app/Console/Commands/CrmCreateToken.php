<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrmCreateToken extends Command
{
    /**
     * @var string
     */
    protected $signature = 'crm:create-token
                            {email? : If omitted, uses the first user (by id)}';

    /**
     * @var string
     */
    protected $description = 'Create a Sanctum personal access token with the `crm` ability for the new CRM (paste into BLACKWIDOW_CRM_API_TOKEN)';

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

        $name = 'crm-'.now()->format('Y-m-d_His');
        $token = $user->createToken($name, ['crm']);

        $this->info('User: '.$user->email);
        $this->line('Token (only shown once — add to BLACKWIDOW_CRM_API_TOKEN in the CRM env):');
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
