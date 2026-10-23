<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every site is now created with Forge zero-downtime deployments, so every template is written
 * against Forge's release macros: build inside $FORGE_RELEASE_DIRECTORY, then $ACTIVATE_RELEASE().
 * Where a type already has live sites, the template is the script those sites deploy with.
 * The renderer still adds the per-site deploy lock, so it is not part of the templates.
 * SSL ONLY (8) and InformationCollector (11) deploy no code and keep no template.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->templates() as $subscriptionTypeId => $script) {
            if (! DB::table('subscription_types')->where('id', $subscriptionTypeId)->exists()) {
                continue;
            }

            $existing = DB::table('deployment_templates')->where('subscription_type_id', $subscriptionTypeId)->first();

            if ($existing) {
                DB::table('deployment_templates')->where('id', $existing->id)->update([
                    'script' => $script,
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('deployment_templates')->insert([
                'subscription_type_id' => $subscriptionTypeId,
                'script' => $script,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // The previous in-place templates cannot run on zero-downtime sites; there is nothing to restore.
    }

    /**
     * @return array<int, string>
     */
    private function templates(): array
    {
        $laravelWithViteAndSync = <<<'BASH'
        $CREATE_RELEASE()

        cd $FORGE_RELEASE_DIRECTORY

        $FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader

        if [ -f artisan ]; then
            $FORGE_PHP artisan migrate --force
            $FORGE_PHP artisan storage:link
        fi

        npm install && npm run build

        $FORGE_PHP artisan view:clear
        $FORGE_PHP artisan route:cache

        $ACTIVATE_RELEASE()

        $FORGE_PHP artisan cache:clear
        $RESTART_QUEUES()

        $FORGE_PHP artisan db:seed DeploySeeder --force
        $FORGE_PHP artisan app:sync-system

        BASH;

        return [
            // Console
            1 => <<<'BASH'
            $CREATE_RELEASE()

            cd $FORGE_RELEASE_DIRECTORY

            # Install Composer dependencies
            $FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader

            # Run database migrations
            if [ -f artisan ]; then
                $FORGE_PHP artisan migrate --force
                $FORGE_PHP artisan storage:link
                $FORGE_PHP artisan permissions:sync-from-policies
            fi

            $FORGE_PHP artisan view:clear
            $FORGE_PHP artisan route:cache

            $ACTIVATE_RELEASE()

            # Clear shared caches and restart services on the new release
            $FORGE_PHP artisan cache:clear
            $RESTART_QUEUES()
            $FORGE_PHP artisan horizon:terminate

            # Seed the database
            $FORGE_PHP artisan db:seed DeploySeeder --force

            BASH,

            // Firearm Module
            2 => <<<'BASH'
            $CREATE_RELEASE()

            cd $FORGE_RELEASE_DIRECTORY

            $FORGE_COMPOSER install
            if [ -f artisan ]; then
                $FORGE_PHP artisan migrate --force
                $FORGE_PHP artisan storage:link
                $FORGE_PHP artisan permissions:sync-from-policies
            fi
            npm install && npm run build
            $FORGE_PHP artisan config:clear
            $FORGE_PHP artisan route:clear
            $FORGE_PHP artisan view:clear
            $FORGE_PHP artisan route:cache

            $ACTIVATE_RELEASE()

            $FORGE_PHP artisan cache:clear
            $RESTART_QUEUES()
            $FORGE_PHP artisan horizon:terminate

            BASH,

            // Responder APP
            3 => <<<'BASH'
            $CREATE_RELEASE()

            cd $FORGE_RELEASE_DIRECTORY

            . ~/.nvm/nvm.sh
            nvm use 22
            npm install
            npm run build

            $ACTIVATE_RELEASE()

            BASH,

            // Reporter APP
            4 => <<<'BASH'
            $CREATE_RELEASE()

            cd $FORGE_RELEASE_DIRECTORY

            . ~/.nvm/nvm.sh
            nvm use 18
            npm install
            npm run build

            $ACTIVATE_RELEASE()

            BASH,

            // Security APP
            5 => <<<'BASH'
            $CREATE_RELEASE()

            cd $FORGE_RELEASE_DIRECTORY

            . ~/.nvm/nvm.sh
            nvm use 18
            npx update-browserslist-db@latest
            npm install
            npm run build

            $ACTIVATE_RELEASE()

            BASH,

            // Driver APP
            6 => <<<'BASH'
            $CREATE_RELEASE()

            cd $FORGE_RELEASE_DIRECTORY

            npm install
            npm run build

            $ACTIVATE_RELEASE()

            BASH,

            // Survey APP
            7 => <<<'BASH'
            $CREATE_RELEASE()

            cd $FORGE_RELEASE_DIRECTORY

            . ~/.nvm/nvm.sh
            nvm use 18
            npm install
            npm run build

            $ACTIVATE_RELEASE()

            BASH,

            // Time and Attendance Module
            9 => $laravelWithViteAndSync,

            // Stock Module
            10 => <<<'BASH'
            $CREATE_RELEASE()

            cd $FORGE_RELEASE_DIRECTORY

            $FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader

            if [ -f artisan ]; then
                $FORGE_PHP artisan migrate --force
                $FORGE_PHP artisan storage:link
            fi

            npm install && npm run build

            $FORGE_PHP artisan view:clear
            $FORGE_PHP artisan route:cache

            $ACTIVATE_RELEASE()

            $FORGE_PHP artisan cache:clear
            $RESTART_QUEUES()

            $FORGE_PHP artisan db:seed DeploySeeder --force

            BASH,

            // Telegram API
            12 => $laravelWithViteAndSync,

            // Pre Case
            13 => <<<'BASH'
            $CREATE_RELEASE()

            cd $FORGE_RELEASE_DIRECTORY

            $FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

            # public/build is gitignored, so build the frontend here. The vite wayfinder plugin shells out to plain `php`,
            # so give it a shim for the PHP version this site runs.
            PHPSHIM=$(mktemp -d)
            ln -s "$(command -v $FORGE_PHP)" "$PHPSHIM/php"
            npm ci --no-audit --no-fund
            PATH="$PHPSHIM:$PATH" npm run build
            rm -rf "$PHPSHIM"

            if [ -f artisan ]; then
                $FORGE_PHP artisan optimize
                $FORGE_PHP artisan migrate --force
            fi

            $ACTIVATE_RELEASE()

            $RESTART_QUEUES()

            BASH,
        ];
    }
};
