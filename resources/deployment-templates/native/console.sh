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
