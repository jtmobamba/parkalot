# ╔════════════════════════════════════════════════════════════════════╗
# ║         PARKALOT SYSTEM - DOCKER STARTUP SCRIPT                    ║
# ║                                                                    ║
# ║  Automatically resolves port conflicts before starting Docker      ║
# ╚════════════════════════════════════════════════════════════════════╝

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  ParkaLot Docker Startup Script" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Function to check if Docker is running
function Test-DockerRunning {
    try {
        $result = docker info 2>&1
        return $LASTEXITCODE -eq 0
    } catch {
        return $false
    }
}

# Function to check if a port is in use
function Test-PortInUse {
    param([int]$Port)
    $connection = Get-NetTCPConnection -LocalPort $Port -ErrorAction SilentlyContinue
    return $null -ne $connection
}

# Function to get process using a port
function Get-PortProcess {
    param([int]$Port)
    $connection = Get-NetTCPConnection -LocalPort $Port -ErrorAction SilentlyContinue
    if ($connection) {
        $process = Get-Process -Id $connection.OwningProcess -ErrorAction SilentlyContinue
        return $process
    }
    return $null
}

# Function to stop XAMPP MySQL
function Stop-XamppMysql {
    Write-Host "[INFO] Attempting to stop XAMPP MySQL..." -ForegroundColor Yellow

    # Try using XAMPP control
    $xamppPath = "C:\xampp\mysql\bin\mysqladmin.exe"
    if (Test-Path $xamppPath) {
        try {
            & $xamppPath -u root shutdown 2>$null
            Start-Sleep -Seconds 2
            Write-Host "[OK] XAMPP MySQL shutdown command sent" -ForegroundColor Green
        } catch {
            Write-Host "[WARN] Could not use mysqladmin" -ForegroundColor Yellow
        }
    }

    # Kill mysqld process if still running
    $mysqlProcess = Get-Process -Name "mysqld" -ErrorAction SilentlyContinue
    if ($mysqlProcess) {
        Write-Host "[INFO] Stopping mysqld process (PID: $($mysqlProcess.Id))..." -ForegroundColor Yellow
        Stop-Process -Id $mysqlProcess.Id -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
        Write-Host "[OK] MySQL process stopped" -ForegroundColor Green
    }
}

# ─────────────────────────────────────────────────────────────────────
# CHECK DOCKER IS RUNNING
# ─────────────────────────────────────────────────────────────────────
Write-Host "[0/4] Checking if Docker Desktop is running..." -ForegroundColor White

if (-not (Test-DockerRunning)) {
    Write-Host "[ERROR] Docker Desktop is not running!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please start Docker Desktop and wait for it to fully initialize." -ForegroundColor Yellow
    Write-Host "Look for the Docker icon in your system tray." -ForegroundColor Yellow
    Write-Host ""

    # Try to start Docker Desktop
    $dockerPath = "C:\Program Files\Docker\Docker\Docker Desktop.exe"
    if (Test-Path $dockerPath) {
        Write-Host "[INFO] Attempting to start Docker Desktop..." -ForegroundColor Yellow
        Start-Process $dockerPath
        Write-Host "[INFO] Waiting for Docker to start (this may take 30-60 seconds)..." -ForegroundColor Yellow

        $maxWait = 60
        $waited = 0
        while (-not (Test-DockerRunning) -and $waited -lt $maxWait) {
            Start-Sleep -Seconds 5
            $waited += 5
            Write-Host "       Waiting... ($waited seconds)" -ForegroundColor Gray
        }

        if (Test-DockerRunning) {
            Write-Host "[OK] Docker Desktop is now running!" -ForegroundColor Green
        } else {
            Write-Host "[ERROR] Docker Desktop did not start in time." -ForegroundColor Red
            Write-Host "Please start it manually and run this script again." -ForegroundColor Yellow
            exit 1
        }
    } else {
        Write-Host "[ERROR] Docker Desktop not found. Please install it from docker.com" -ForegroundColor Red
        exit 1
    }
} else {
    Write-Host "[OK] Docker Desktop is running" -ForegroundColor Green
}

# ─────────────────────────────────────────────────────────────────────
# CLEANUP EXISTING CONTAINERS FIRST (releases ports)
# ─────────────────────────────────────────────────────────────────────
Write-Host "[CLEANUP] Stopping any existing ParkaLot containers..." -ForegroundColor Yellow
Set-Location $PSScriptRoot
docker-compose down 2>$null
Start-Sleep -Seconds 3

# ─────────────────────────────────────────────────────────────────────
# CHECK PORT 3307 (Docker MySQL - avoids conflict with XAMPP on 3306)
# ─────────────────────────────────────────────────────────────────────
Write-Host "[1/4] Checking port 3307 (Docker MySQL)..." -ForegroundColor White

if (Test-PortInUse -Port 3307) {
    $process = Get-PortProcess -Port 3307
    if ($process) {
        # Skip Docker processes - they're handled by docker-compose down
        if ($process.Name -like "*docker*") {
            Write-Host "[INFO] Port 3307 held by Docker (will be released by cleanup)" -ForegroundColor Yellow
        } else {
            Write-Host "[CONFLICT] Port 3307 is in use by: $($process.Name) (PID: $($process.Id))" -ForegroundColor Red
            Write-Host "[INFO] Stopping process..." -ForegroundColor Yellow
            Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue
            Start-Sleep -Seconds 2
        }
    }
} else {
    Write-Host "[OK] Port 3307 is available" -ForegroundColor Green
}

# Info about XAMPP MySQL
if (Test-PortInUse -Port 3306) {
    Write-Host "[INFO] XAMPP MySQL is running on port 3306 (will not be stopped)" -ForegroundColor Cyan
}

# ─────────────────────────────────────────────────────────────────────
# CHECK PORT 8080 (App)
# ─────────────────────────────────────────────────────────────────────
Write-Host "[2/4] Checking port 8080 (Application)..." -ForegroundColor White

if (Test-PortInUse -Port 8080) {
    $process = Get-PortProcess -Port 8080
    if ($process) {
        # Skip Docker processes - they're handled by docker-compose down
        if ($process.Name -like "*docker*") {
            Write-Host "[INFO] Port 8080 held by Docker (will be released by cleanup)" -ForegroundColor Yellow
        } else {
            Write-Host "[CONFLICT] Port 8080 is in use by: $($process.Name) (PID: $($process.Id))" -ForegroundColor Red
            Write-Host "[INFO] Stopping process..." -ForegroundColor Yellow
            Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue
            Start-Sleep -Seconds 2
        }
    }
} else {
    Write-Host "[OK] Port 8080 is available" -ForegroundColor Green
}

# ─────────────────────────────────────────────────────────────────────
# CHECK PORT 8081 (phpMyAdmin)
# ─────────────────────────────────────────────────────────────────────
Write-Host "[3/4] Checking port 8081 (phpMyAdmin)..." -ForegroundColor White

if (Test-PortInUse -Port 8081) {
    $process = Get-PortProcess -Port 8081
    if ($process) {
        # Skip Docker processes - they're handled by docker-compose down
        if ($process.Name -like "*docker*") {
            Write-Host "[INFO] Port 8081 held by Docker (will be released by cleanup)" -ForegroundColor Yellow
        } else {
            Write-Host "[CONFLICT] Port 8081 is in use by: $($process.Name) (PID: $($process.Id))" -ForegroundColor Red
            Write-Host "[INFO] Stopping process..." -ForegroundColor Yellow
            Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue
            Start-Sleep -Seconds 2
        }
    }
} else {
    Write-Host "[OK] Port 8081 is available" -ForegroundColor Green
}

# ─────────────────────────────────────────────────────────────────────
# START DOCKER COMPOSE
# ─────────────────────────────────────────────────────────────────────
Write-Host "[4/4] Starting Docker Compose..." -ForegroundColor White
Write-Host ""

# Change to script directory
Set-Location $PSScriptRoot

# Start containers
Write-Host "[INFO] Building and starting containers..." -ForegroundColor Yellow
docker-compose up --build -d

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "  ParkaLot Started Successfully!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "  Application:  http://localhost:8080" -ForegroundColor Cyan
    Write-Host "  phpMyAdmin:   http://localhost:8081" -ForegroundColor Cyan
    Write-Host "  Docker MySQL: localhost:3307" -ForegroundColor Cyan
    Write-Host "  XAMPP MySQL:  localhost:3306 (if running)" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "  To stop: docker-compose down" -ForegroundColor Yellow
    Write-Host ""
} else {
    Write-Host ""
    Write-Host "[ERROR] Failed to start Docker containers" -ForegroundColor Red
    Write-Host "Run 'docker-compose logs' to see errors" -ForegroundColor Yellow
    exit 1
}
