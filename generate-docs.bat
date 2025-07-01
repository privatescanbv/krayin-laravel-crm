@echo off
REM Use this script to generate the documentation locally (for development purposes)

REM Run the doc.sh script using Git Bash or WSL (indien beschikbaar)
bash ./doc.sh

REM Pad naar HTML-bestand
set HTML_FILE=docs\html\index.html

REM Controleer of het bestand bestaat
if not exist "%HTML_FILE%" (
    echo Bestand "%HTML_FILE%" niet gevonden.
    exit /b 1
)

REM Vervang alle /admin/docs/resources/ naar ./resources/ in het HTML-bestand
powershell -Command "(Get-Content '%HTML_FILE%') -replace '/admin/docs/resources/', './resources/' | Set-Content '%HTML_FILE%'"
