@echo off
echo ╔════════════════════════════════════════════════════════════════════╗
echo ║              PARKALOT SYSTEM - DOCKER LAUNCHER                     ║
echo ╚════════════════════════════════════════════════════════════════════╝
echo.

REM Check if Docker is running
docker info >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Docker is not running. Please start Docker Desktop first.
    pause
    exit /b 1
)

echo [INFO] Docker is running.
echo.

REM Check if XAMPP MySQL is running on port 3306 or 8081
echo [INFO] Checking MySQL connection...
echo [NOTE] Make sure XAMPP MySQL is running before continuing.
echo.

REM Stop any existing containers
echo [INFO] Stopping existing ParkaLot containers...
docker-compose -f docker-compose.host-db.yml down 2>nul

echo.
echo [INFO] Building and starting ParkaLot container...
docker-compose -f docker-compose.host-db.yml up -d --build

if errorlevel 1 (
    echo.
    echo [ERROR] Failed to start container. Check the error messages above.
    pause
    exit /b 1
)

echo.
echo ╔════════════════════════════════════════════════════════════════════╗
echo ║                    PARKALOT IS NOW RUNNING!                        ║
echo ╠════════════════════════════════════════════════════════════════════╣
echo ║  Frontend:  http://localhost:8080                                  ║
echo ║  API:       http://localhost:8080/api/index.php                    ║
echo ║  MySQL:     Connected to XAMPP (host.docker.internal)              ║
echo ╠════════════════════════════════════════════════════════════════════╣
echo ║  To stop:   docker-compose -f docker-compose.host-db.yml down      ║
echo ║  Logs:      docker logs parkalot_app                               ║
echo ╚════════════════════════════════════════════════════════════════════╝
echo.

REM Wait a moment for container to start
timeout /t 5 /nobreak >nul

REM Open browser
echo [INFO] Opening browser...
start http://localhost:8080

pause
