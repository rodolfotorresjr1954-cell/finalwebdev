@echo off
echo Starting Burger API for mobile devices on http://0.0.0.0:8000
echo Use this IP in ACT1\src\config\api.ts - API_HOST_OVERRIDE
echo Google OAuth redirect (already in Console): http://127.0.0.1:8000/connect/google/check
echo.
ipconfig | findstr /i "IPv4"
cd /d "%~dp0"
php -S 0.0.0.0:8000 -t public
