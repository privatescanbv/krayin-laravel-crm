#!/bin/bash
# builds the frontend assets for the project (root Vite config covers Admin package)
# Run this script inside the docker container! otherwise incorrect rollover dependencies may be installed

set -e

#rm -rf node_modules .vite node_modules/.vite

# clean up
rm -rf node_modules yarn.lock package-lock.json .vite node_modules/.vite

#install  --immutable
yarn install

# production build, not used locally. More to identify build issues before deployment.
ROLLUP_SKIP_NODEJS_NATIVE=true yarn build

# Build Admin package
cd packages/Webkul/Admin
npm install && npm run build
cd ../../..

# Build WebForm package
cd packages/Webkul/WebForm
npm install && npm run build
cd ../../..

# do not build installer, do not use it for now

echo "DONE"

