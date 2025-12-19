@echo off
title Iniciador de Servicios Laravel
color 0A

echo =========================================
echo    INICIADOR DE SERVICIOS LARAVEL
echo =========================================
echo.

REM Verificar si estamos en el directorio correcto
if not exist artisan (
    echo Error: No se encuentra el archivo artisan
    echo Por favor, ejecuta este script desde la raiz del proyecto Laravel
    pause
    exit /b 1
)

REM Verificar si Node.js está instalado
where npm >nul 2>nul
if errorlevel 1 (
    echo Error: npm no encontrado. Por favor instala Node.js
    pause
    exit /b 1
)

echo 1. Iniciando Laravel Serve...
start "Laravel Server - localhost:8000" cmd /c "php artisan serve && pause"

timeout /t 3 /nobreak > nul

echo 2. Iniciando Laravel Reverb...
start "Laravel Reverb" cmd /c "php artisan reverb:start && pause"

timeout /t 3 /nobreak > nul

echo 3. Iniciando Laravel Queue Worker...
start "Laravel Queue" cmd /c "php artisan queue:work && pause"

timeout /t 3 /nobreak > nul

echo 4. Iniciando Vite Dev Server...
start "Vite Dev Server" cmd /c "npm run dev && pause"

timeout /t 3 /nobreak > nul

echo 5. Iniciando Laravel Scheduler...
start "Laravel Scheduler" cmd /c "echo Scheduler ejecutandose cada minuto... && php artisan schedule:work && pause"

echo.
echo =========================================
echo    Todos los servicios han sido iniciados
echo =========================================
echo.
echo Servicios en ejecucion:
echo - Laravel: http://localhost:8000
echo - Reverb:  ws://localhost:8080
echo - Vite:    http://localhost:5173
echo - Queue:   Trabajando en segundo plano
echo - Schedule: Ejecutandose cada minuto
echo.
echo Presiona cualquier tecla para cerrar este menu...
pause > nul