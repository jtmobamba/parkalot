@echo off
REM ╔════════════════════════════════════════════════════════════════════╗
REM ║     PARKALOT - Stop Docker Container (Host MySQL mode)            ║
REM ╚════════════════════════════════════════════════════════════════════╝

echo.
echo ========================================
echo   Stopping ParkaLot Docker Container
echo ========================================
echo.

docker-compose -f docker-compose.host-db.yml down

if %ERRORLEVEL% equ 0 (
    echo.
    echo [SUCCESS] ParkaLot container stopped.
    echo.
) else (
    echo.
    echo [ERROR] Failed to stop container.
)

pause
