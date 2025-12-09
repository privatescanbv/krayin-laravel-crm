#!/bin/bash
set -euo pipefail

# Stel de variabelen in
IMAGE_NAME="krayincrm"
GITHUB_USERNAME="privatescanbv"
REPO_NAME="krayin-laravel-crm"
GITHUB_REGISTRY="ghcr.io"
TAG="latest"

./doc.sh

# Bouw de Docker image met een specifieke Dockerfile
docker build --platform linux/amd64 -t $IMAGE_NAME -f ./docker/php/Dockerfile .

# Tag de Docker image voor GitHub Container Registry
docker tag $IMAGE_NAME $GITHUB_REGISTRY/$GITHUB_USERNAME/$REPO_NAME/$IMAGE_NAME:$TAG

echo "Docker image $IMAGE_NAME tag to GitHub Packages at $GITHUB_REGISTRY/$GITHUB_USERNAME/$REPO_NAME."

# Push de Docker image naar GitHub Container Registry
docker push $GITHUB_REGISTRY/$GITHUB_USERNAME/$REPO_NAME/$IMAGE_NAME:$TAG

echo "Docker image $IMAGE_NAME pushed to GitHub Packages at $GITHUB_REGISTRY/$GITHUB_USERNAME/$REPO_NAME/$IMAGE_NAME:$TAG."
