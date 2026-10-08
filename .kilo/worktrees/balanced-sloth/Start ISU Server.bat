@echo off
title ISU Supply System - Launcher
echo ==================================================
echo    ISU Cauayan Supply Management System
echo    Starting everything...
echo ==================================================
echo.

echo [1/3] Starting MySQL database...
netstat -an | find ":3306" | find "LISTENING" >nul
if %errorlevel%==0 (
    echo       MySQL is already running.
) else (
    start "ISU MySQL" /min "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
    timeout /t 4 /nobreak >nul
    echo       MySQL started.
)

echo [2/3] Starting web server...
start "ISU Web Server" "%~dp0web\run-web.bat"

echo [3/3] Starting notification worker...
start "ISU Notification Worker" "%~dp0web\run-queue.bat"

echo.
echo ==================================================
echo    READY!
echo.
echo    On THIS computer:   http://127.0.0.1:8000
echo    From phone/WiFi:    http://YOUR-PC-IP:8000
echo.
echo    Login: admin@isu.edu.ph   Password: password
echo ==================================================
echo.
echo Two new windows opened (Web Server + Worker).
echo Keep them open while using the system.
echo Close them (or this window) to stop the server.
echo.
pause
