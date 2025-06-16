#!/bin/bash

docker run --rm -v "$(pwd)/docs:/documents/" -v "$(pwd)/.cache:/tmp/.cache" --env HOME="${pwd}" -e XDG_CACHE_HOME=/tmp/.cache -u $(id -u) asciidoctor/docker-asciidoctor:latest ./doc.sh
