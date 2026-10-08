@echo off
title ISU Web Server
set "PATH=C:\tools\php-8.3;C:\tools\composer;%PATH%"
cd /d "%~dp0"
echo ============================================
echo   ISU Supply System - WEB SERVER
echo   http://127.0.0.1:8000
echo   Keep this window OPEN while using the system.
echo ============================================
echo.
php artisan serve --host=0.0.0.0 --port=8000
pause
