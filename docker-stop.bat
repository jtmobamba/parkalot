@echo off
echo ╔════════════════════════════════════════════════════════════════════╗
echo ║              PARKALOT SYSTEM - DOCKER SHUTDOWN                     ║
echo ╚════════════════════════════════════════════════════════════════════╝
echo.

echo [INFO] Stopping ParkaLot containers...
docker-compose -f docker-compose.host-db.yml down

echo.
echo [INFO] ParkaLot containers stopped.
echo.
pause
