@echo off
cd /d C:\xampp\htdocs\cabcontrolmx

:loop
C:\xampp\php\php.exe artisan orbanamx:dispatch-tracks-tick --tenant=1 --sleep=5 --max-seconds=55
timeout /t 2 /nobreak >nul
goto loop
