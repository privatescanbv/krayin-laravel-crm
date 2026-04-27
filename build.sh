#!/bin/bash
# builds the frontend assets for the project (root Vite config covers Admin package)
# Run this script inside the docker container! otherwise incorrect rollover dependencies may be installed
# Usage: ./build.sh [local|production]
#   local: skip npm run build commands (for development)
#   production: run all build commands (default)

set -e

# Get build type from first argument, default to production
BUILD_TYPE=${1:-production}

#rm -rf node_modules .vite node_modules/.vite

# Build Admin package
cd packages/Webkul/Admin
npm install
if [ "$BUILD_TYPE" != "local" ]; then
    npm run build
fi
cd ../../..

# do not build installer, do not use it for now

echo "DONE (BUILD_TYPE: $BUILD_TYPE)"

