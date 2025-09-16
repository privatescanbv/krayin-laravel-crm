echo off
REM Windows batch equivalent of doc.sh
REM Runs AsciiDoctor in Docker container to process documentation

docker run --rm -v "%cd%/docs:/documents/" -v "%cd%/.cache:/tmp/.cache" --env HOME="/tmp" -e XDG_CACHE_HOME=/tmp/.cache asciidoctor/docker-asciidoctor:latest ./doc.sh


@REM @echo off
@REM setlocal ENABLEDELAYEDEXPANSION
@REM
@REM rem Ensure cache directory exists
@REM if not exist ".cache" mkdir ".cache"
@REM
@REM rem Convert Windows path (e.g., C:\path) to Docker-friendly (/c/path)
@REM set "PWD_WIN=%cd%"
@REM for /f "tokens=1,2 delims=:" %%a in ("%PWD_WIN%") do (
@REM   set "DRIVE=%%a"
@REM   set "REST=%%b"
@REM )
@REM set "REST=!REST:\=/!"
@REM set "MOUNT=/%DRIVE%!REST!"
@REM
@REM docker run --rm -v "%MOUNT%/docs:/documents" -v "%MOUNT%/.cache:/tmp/.cache" -e XDG_CACHE_HOME=/tmp/.cache asciidoctor/docker-asciidoctor:latest ./doc.sh
@REM
@REM endlocal
