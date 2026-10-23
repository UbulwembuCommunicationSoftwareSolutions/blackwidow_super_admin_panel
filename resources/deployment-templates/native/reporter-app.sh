$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

. ~/.nvm/nvm.sh
nvm use 18
npm install
npm run build

$ACTIVATE_RELEASE()
