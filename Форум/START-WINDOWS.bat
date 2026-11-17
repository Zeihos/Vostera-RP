@echo off
chcp 65001 >nul
cd /d "%~dp0"
where php >nul 2>nul
if errorlevel 1 (
 echo PHP не найден. Установите PHP 8.1+ и добавьте его в PATH.
 pause
 exit /b 1
)
echo VOSTERA ROLEPLAY ^| http://127.0.0.1:8787/install
start "" "http://127.0.0.1:8787/install"
php -S 127.0.0.1:8787 -t public public/router.php
pause
