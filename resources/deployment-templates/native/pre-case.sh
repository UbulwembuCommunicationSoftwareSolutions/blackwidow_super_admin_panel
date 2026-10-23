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
