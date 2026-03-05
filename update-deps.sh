#!/bin/bash

set -e

echo "Updating Composer dependencies..."

composer update

echo "Running autoload optimization..."
composer dump-autoload -o

echo "Composer dependencies updated."


echo "Updating and install npm dependencies root..."

npm upgrade
npm install

echo "Building Admin package..."

cd packages/Webkul/Admin
echo "Updating dependencies..."
npm update
echo "Installing dependencies..."
npm install
cd ../../..

echo "Building WebForm package..."
cd packages/Webkul/WebForm
echo "Updating dependencies..."
npm update
echo "Installing dependencies..."
npm install
cd ../../..

echo "Packages build completed."
