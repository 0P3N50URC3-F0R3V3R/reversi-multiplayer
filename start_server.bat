@echo off
echo Reversi szerver indul: http://localhost:8000
echo Leallitashoz: Ctrl+C
php\php.exe -c php\php.ini -S localhost:8000 -t .
