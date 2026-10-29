@echo off
title Hospital Management System - Desktop Launcher
cd /d "%~dp0"

echo ==========================================================
echo    Hospital Management System (HMS) - Desktop Edition
echo ==========================================================
echo.

:: 1. Locate PHP executable
set PHP_BIN=
if exist "C:\xampp\php\php.exe" set PHP_BIN=C:\xampp\php\php.exe
if "%PHP_BIN%"=="" (
    for /f "delims=" %%i in ('where php 2^>nul') do set PHP_BIN=%%i
)

if "%PHP_BIN%"=="" (
    echo [ERROR] PHP executable was not found!
    echo Please install XAMPP at C:\xampp or add php to your system PATH.
    pause
    exit /b 1
)

:: 2. Check if MySQL is running
echo [*] Checking MySQL Database connection...
netstat -ano | findstr ":3306 " >nul
if errorlevel 1 (
    echo [*] MySQL is not active. Attempting to start XAMPP MySQL...
    if exist "C:\xampp\mysql\bin\mysqld.exe" (
        start "" "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
        timeout /t 3 /nobreak >nul
    ) else (
        echo [WARNING] MySQL is not running and XAMPP mysqld.exe was not found.
        echo Please ensure your MySQL database is started.
    )
) else (
    echo [OK] MySQL is active on port 3306.
)

:: 3. Port configuration
set PORT=8765

:: 4. Stop any previous instance running on port 8765
for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":%PORT% "') do (
    taskkill /F /PID %%a >nul 2>&1
)

:: 5. Launch PHP Built-in Server in background
echo [*] Starting HMS Application Server on port %PORT%...
start /B "" "%PHP_BIN%" -S 127.0.0.1:%PORT% -t "%~dp0" >nul 2>&1

:: Wait for server to bind
timeout /t 2 /nobreak >nul

:: 6. Locate Edge or Chrome for dedicated App Window
set BROWSER_CMD=
if exist "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" (
    set BROWSER_CMD="%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe"
) else if exist "%ProgramFiles%\Microsoft\Edge\Application\msedge.exe" (
    set BROWSER_CMD="%ProgramFiles%\Microsoft\Edge\Application\msedge.exe"
) else if exist "%ProgramFiles%\Google\Chrome\Application\chrome.exe" (
    set BROWSER_CMD="%ProgramFiles%\Google\Chrome\Application\chrome.exe"
) else if exist "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" (
    set BROWSER_CMD="%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe"
)

echo [*] Launching Native Desktop Window...
if not "%BROWSER_CMD%"=="" (
    start "" %BROWSER_CMD% --app=http://127.0.0.1:%PORT%/ --window-size=1366,860 --app-id=hms-desktop-app
) else (
    start http://127.0.0.1:%PORT%/
)

echo [OK] HMS Desktop is running!
exit /b 0
