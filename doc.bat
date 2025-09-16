@echo off
setlocal ENABLEDELAYEDEXPANSION

rem Ensure cache directory exists
if not exist ".cache" mkdir ".cache"

rem Convert Windows path (e.g., C:\path) to Docker-friendly (/c/path)
set "PWD_WIN=%cd%"
for /f "tokens=1,2 delims=:" %%a in ("%PWD_WIN%") do (
  set "DRIVE=%%a"
  set "REST=%%b"
)
set "REST=!REST:\=/!"
set "MOUNT=/%DRIVE%!REST!"

docker run --rm -v "%MOUNT%/docs:/documents" -v "%MOUNT%/.cache:/tmp/.cache" -e XDG_CACHE_HOME=/tmp/.cache asciidoctor/docker-asciidoctor:latest ./doc.sh

endlocal
