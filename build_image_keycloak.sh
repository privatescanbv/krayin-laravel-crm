#!/bin/bash
set -euo pipefail

# Stel de variabelen in
IMAGE_NAME="keycloak"
GITHUB_USERNAME="privatescanbv"
REPO_NAME="krayin-laravel-crm"
GITHUB_REGISTRY="ghcr.io"

BRANCH="${GITHUB_REF_NAME:-$(git rev-parse --abbrev-ref HEAD)}"
SHORT_SHA=$(git rev-parse --short HEAD)

./doc.sh

docker build --platform linux/amd64 \
  -t $IMAGE_NAME \
  -f docker/php/Dockerfile .

FULL_IMAGE="$GITHUB_REGISTRY/$GITHUB_USERNAME/$REPO_NAME/$IMAGE_NAME"

if [ "$BRANCH" = "development" ]; then
  docker tag $IMAGE_NAME $FULL_IMAGE:latest
  docker push $FULL_IMAGE:latest

elif [ "$BRANCH" = "main" ]; then
  # immutable tag
  docker tag $IMAGE_NAME $FULL_IMAGE:prod-$SHORT_SHA
  docker push $FULL_IMAGE:prod-$SHORT_SHA

  # moving production pointer
  docker tag $IMAGE_NAME $FULL_IMAGE:prod
  docker push $FULL_IMAGE:prod
fi
