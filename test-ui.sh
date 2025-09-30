#!/bin/bash

cd UiTests || exit 1

read -p "Clean build? (y/n): " answer

if [[ "$answer" == "y" || "$answer" == "Y" ]]; then
  echo "Performing clean build..."
  rm -rf ~/Library/Caches/ms-playwright
  dotnet clean
  rm -rf bin obj
  dotnet restore
fi

dotnet build
dotnet test
#dotnet test --filter "FullyQualifiedName~PartnerProduct"

cd ..
