$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

. ~/.nvm/nvm.sh
nvm use 18
npx update-browserslist-db@latest
npm install
npm run build

$ACTIVATE_RELEASE()
