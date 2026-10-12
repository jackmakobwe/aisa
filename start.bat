@echo off
rem Starts the Aisa development server so this PC and devices on the same Wi-Fi can open it.
rem Double-click to run. Close this window (or press Ctrl+C) to stop the site.

cd /d "%~dp0"
title Aisa dev server

set "LAN_IP="
for /f "usebackq delims=" %%i in (`powershell -NoProfile -Command "(Get-NetIPAddress -AddressFamily IPv4 -PrefixOrigin Dhcp -ErrorAction SilentlyContinue | Select-Object -First 1).IPAddress"`) do set "LAN_IP=%%i"

if not exist "public\build\manifest.json" (
    echo Building front-end assets for the first time...
    call npm run build
)

echo.
echo   Aisa is starting...
echo.
echo   On this PC:        http://localhost:8000
if defined LAN_IP echo   On other devices:  http://%LAN_IP%:8000
echo   Dashboard:         http://localhost:8000/admin
echo.
echo   Keep this window open. Close it or press Ctrl+C to stop the site.
echo.

rem Open the browser a few seconds after the server starts.
start "" /b cmd /c "timeout /t 3 /nobreak >nul & start http://localhost:8000"

php artisan serve --host=0.0.0.0 --port=8000

pause
