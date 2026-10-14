
@echo off
title CapstoneSystem - Laravel Queue Worker

cd /d "%~dp0"

if not exist "artisan" (
    echo ERROR: Laravel artisan file not found.
    pause
    exit /b 1
)

C:\xampp\php\php.exe artisan queue:work

pause
