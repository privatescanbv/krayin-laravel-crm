#!/bin/bash

set -e

echo "Updating Composer dependencies..."

composer update

echo "Running autoload optimization..."
composer dump-autoload -o

echo "Composer dependencies updated."


echo "Building Admin package..."

# De Admin package is de enige met een package.json / vite.config.js.
cd packages/Webkul/Admin
echo "Updating dependencies..."
npm update
echo "Installing dependencies..."
npm install
cd ../../..

echo "Packages build completed."
