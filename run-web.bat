
@echo off
title CapstoneSystem - Laravel Web Server

cd /d "%~dp0"

if not exist "artisan" (
    echo ERROR: Laravel artisan file not found.
    echo Make sure this script is inside the Laravel project root.
    pause
    exit /b 1
)

C:\xampp\php\php.exe artisan serve --host=0.0.0.0 --port=8000

pause
