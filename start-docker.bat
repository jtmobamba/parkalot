@echo off
:: ╔════════════════════════════════════════════════════════════════════╗
:: ║         PARKALOT SYSTEM - DOCKER STARTUP (RUN AS ADMIN)           ║
:: ╚════════════════════════════════════════════════════════════════════╝

:: Check for admin rights
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo Requesting administrator privileges...
    powershell -Command "Start-Process '%~f0' -Verb RunAs"
    exit /b
)

:: Run the PowerShell script
cd /d "%~dp0"
powershell -ExecutionPolicy Bypass -File "%~dp0start-docker.ps1"
pause
