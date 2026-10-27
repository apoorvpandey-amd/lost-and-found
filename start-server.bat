@echo off
title FindIt - Lost & Found Web Portal
cd /d "%~dp0"

echo ========================================================
echo   FindIt - Lost & Found Portal
echo   Starting local PHP development server...
echo ========================================================
echo.
echo Opening browser at http://localhost:8000/index.php
echo Press Ctrl+C in this command window to stop the server.
echo.

start http://localhost:8000/index.php

where php >nul 2>nul
if %errorlevel% equ 0 (
    php -S localhost:8000
) else (
    "%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" -S localhost:8000
)

pause
