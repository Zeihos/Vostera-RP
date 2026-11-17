@echo off
chcp 65001 >nul
cd /d "%~dp0"
where php >nul 2>&1
if errorlevel 1 (
 echo [VOSTERA WIKI] PHP not found. Install PHP 8.1+ and add php.exe to PATH.
 pause
 exit /b 1
)
echo [VOSTERA WIKI] Starting local web server...
echo [VOSTERA WIKI] Open http://127.0.0.1:8788/install
php -S 127.0.0.1:8788 -t public public/router.php
pause
