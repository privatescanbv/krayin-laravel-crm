@echo off
REM Windows batch equivalent of doc.sh
REM Runs AsciiDoctor in Docker container to process documentation

docker run --rm -v "%cd%/docs:/documents/" -v "%cd%/.cache:/tmp/.cache" --env HOME="/tmp" -e XDG_CACHE_HOME=/tmp/.cache asciidoctor/docker-asciidoctor:latest ./doc.sh