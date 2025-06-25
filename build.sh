#!/bin/bash

# builds the frontend assets for the project

npm install && npm run build
cd packages/Webkul/Admin || exit
npm install && npm run build
cd ../../..

echo "DONE"
