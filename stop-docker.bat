@echo off
:: ╔════════════════════════════════════════════════════════════════════╗
:: ║         PARKALOT SYSTEM - DOCKER STOP                             ║
:: ╚════════════════════════════════════════════════════════════════════╝

cd /d "%~dp0"
echo Stopping ParkaLot containers...
docker-compose down
echo.
echo ParkaLot containers stopped.
pause
