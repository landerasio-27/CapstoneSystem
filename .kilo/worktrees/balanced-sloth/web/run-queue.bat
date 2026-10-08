@echo off
title ISU Notification Worker
set "PATH=C:\tools\php-8.3;C:\tools\composer;%PATH%"
cd /d "%~dp0"
echo ============================================
echo   ISU Supply System - NOTIFICATION WORKER
echo   (Sends the announcement push notifications)
echo   Keep this window OPEN while using the system.
echo ============================================
echo.
php artisan queue:work
pause
