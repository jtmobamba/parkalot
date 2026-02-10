@echo off
REM ╔════════════════════════════════════════════════════════════════════╗
REM ║     PARKALOT - Start Docker with Host MySQL (XAMPP)               ║
REM ╚════════════════════════════════════════════════════════════════════╝

echo.
echo ========================================
echo   ParkaLot Docker Startup (Host MySQL)
echo ========================================
echo.

REM Check if Docker is running
docker info >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo [ERROR] Docker is not running. Please start Docker Desktop first.
    pause
    exit /b 1
)

echo [INFO] Building and starting ParkaLot container...
echo [INFO] This will connect to your XAMPP MySQL on localhost:3306
echo.

REM Build and start the container
docker-compose -f docker-compose.host-db.yml up -d --build

if %ERRORLEVEL% equ 0 (
    echo.
    echo ========================================
    echo   SUCCESS! ParkaLot is running
    echo ========================================
    echo.
    echo   Application: http://localhost:8080
    echo   MySQL:       Using XAMPP MySQL (localhost:3306)
    echo.
    echo   To stop: run stop-docker-hostdb.bat
    echo   To view logs: docker logs -f parkalot_app
    echo.
) else (
    echo.
    echo [ERROR] Failed to start container. Check the error messages above.
)

pause
