@echo off
title Reversi – Szerver
echo.
echo  ==========================================
echo   Reversi Tobbjatekos – Szerver indul...
echo  ==========================================
echo.
echo  URL: http://localhost:8000
echo  Leallitas: Ctrl+C
echo.

:: Check if port already in use
netstat -an 2>nul | find "0.0.0.0:8000" >nul
if %errorlevel% == 0 (
    echo  [WARN] 8000-es port mar foglalt!
    echo  Megnyitom a bongeszot...
    start http://localhost:8000
    pause
    exit /b 1
)

:: Open browser after short delay (background)
start "" cmd /c "timeout /t 2 >nul && start http://localhost:8000"

:: Start PHP server (blocking)
php\php.exe -c php\php.ini -S localhost:8000 -t .
