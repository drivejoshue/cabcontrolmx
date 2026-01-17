@echo off
:loop
php artisan orbanamx:autodispatch-tick
timeout /t 3 /nobreak > nul
goto loop
