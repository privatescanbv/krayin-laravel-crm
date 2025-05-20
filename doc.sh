#!/bin/bash

docker run --rm -v "$(pwd)/docs:/documents/" --env HOME="${pwd}" -u $(id -u) asciidoctor/docker-asciidoctor:latest ./doc.sh
