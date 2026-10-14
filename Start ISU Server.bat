
@echo off
setlocal
title ISU Supply System - Launcher

cd /d "%~dp0"

echo ==================================================
echo    ISU Cauayan Supply Management System
echo    Starting everything...
echo ==================================================
echo.

echo [1/3] Starting MySQL database...
netstat -an | findstr /R /C:":3306 .*LISTENING" >nul

if %errorlevel%==0 (
    echo       MySQL is already running.
) else (
    if exist "C:\xampp\mysql\bin\mysqld.exe" (
        start "ISU MySQL" /min "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
        timeout /t 4 /nobreak >nul
        echo       MySQL startup requested.
    ) else (
        echo       ERROR: MySQL executable not found.
        echo       Please check your XAMPP installation.
    )
)

echo.
echo [2/3] Starting web server...

if exist "%~dp0run-web.bat" (
    start "ISU Web Server" cmd /k call "%~dp0run-web.bat"
) else (
    echo       ERROR: run-web.bat not found in:
    echo       %~dp0
)

echo.
echo [3/3] Starting notification worker...

if exist "%~dp0run-queue.bat" (
    start "ISU Notification Worker" cmd /k call "%~dp0run-queue.bat"
) else (
    echo       ERROR: run-queue.bat not found in:
    echo       %~dp0
)

echo.
echo ==================================================
echo    STARTUP COMMANDS SENT
echo.
echo    On THIS computer:   http://127.0.0.1:8000
echo    From phone/WiFi:    http://YOUR-PC-IP:8000
echo ==================================================
echo.
echo Keep the Web Server and Worker windows open.
echo Check those windows for any Laravel errors.
echo.
pause
