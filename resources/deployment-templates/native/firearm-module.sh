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
