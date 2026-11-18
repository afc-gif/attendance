# ==============================================================================
# ARTSCI Attendance - Automated GitHub Deployment for Windows 10 Server
# Location: C:\ARTSCI\Attendance\deploy\windows\auto-deploy-github.ps1
# Runs periodically (every 3 minutes) via Windows Task Scheduler.
# ==============================================================================

param (
    [string]$AppRoot = "C:\ARTSCI\Attendance",
    [string]$PhpExe = "C:\PHP85\php.exe",
    [string]$Branch = "main",
    [string]$Remote = "origin",
    [string]$HealthCheckUrl = "http://127.0.0.1:8080/up"
)

# 1. Resolve Application Directory
if (-not (Test-Path "$AppRoot\artisan")) {
    $scriptParent = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
    if (Test-Path "$scriptParent\artisan") {
        $AppRoot = $scriptParent
    } else {
        $AppRoot = Get-Location
    }
}

# 2. Resolve PHP Executable
if (-not (Test-Path $PhpExe)) {
    $phpCmd = Get-Command php -ErrorAction SilentlyContinue
    if ($phpCmd) {
        $PhpExe = $phpCmd.Source
    } else {
        $possiblePaths = @("C:\PHP85\php.exe", "C:\PHP\php.exe", "C:\tools\php85\php.exe", "C:\tools\php83\php.exe")
        foreach ($p in $possiblePaths) {
            if (Test-Path $p) {
                $PhpExe = $p
                break
            }
        }
    }
}

$logFile = Join-Path $AppRoot "storage\logs\auto-deploy.log"
$lockFile = Join-Path $AppRoot "storage\framework\deploy.lock"

function Write-DeployLog {
    param ([string]$Message, [string]$Level = "INFO")
    $logLine = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') [$Level] $Message"
    Write-Host $logLine
    $logDir = Split-Path $logFile -Parent
    if (-not (Test-Path $logDir)) {
        New-Item -ItemType Directory -Path $logDir -Force | Out-Null
    }
    Add-Content -Path $logFile -Value $logLine -ErrorAction SilentlyContinue
}

# 3. Mutex / Lock Mechanism (Prevent concurrent deployments)
if (Test-Path $lockFile) {
    $lockAge = (Get-Date) - (Get-Item $lockFile).LastWriteTime
    if ($lockAge.TotalMinutes -lt 20) {
        # Active deployment in progress, exit silently
        exit 0
    }
    Write-DeployLog "Found stale deploy lock ($([math]::Round($lockAge.TotalMinutes, 1)) min old). Clearing stale lock." "WARNING"
    Remove-Item $lockFile -Force -ErrorAction SilentlyContinue
}

# Create lockfile
$lockDir = Split-Path $lockFile -Parent
if (-not (Test-Path $lockDir)) {
    New-Item -ItemType Directory -Path $lockDir -Force | Out-Null
}
Set-Content -Path $lockFile -Value "PID: $PID; Started: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"

try {
    Set-Location $AppRoot

    # 4. Verify Git availability and working directory
    $gitCmd = Get-Command git -ErrorAction SilentlyContinue
    if (-not $gitCmd) {
        Write-DeployLog "Git is not installed or not found in system PATH. Cannot deploy." "ERROR"
        throw "Git not found"
    }

    if (-not (Test-Path ".git")) {
        Write-DeployLog "'$AppRoot' is not a Git repository. Cannot deploy." "ERROR"
        throw "Not a git repository"
    }

    # Verify .env exists and is protected
    if (-not (Test-Path ".env")) {
        Write-DeployLog "CRITICAL: .env file missing in '$AppRoot'! Aborting deployment to protect server." "ERROR"
        throw ".env missing"
    }

    # Use the dedicated SYSTEM GitHub deployment SSH identity
    $env:GIT_SSH = "C:\Windows\System32\OpenSSH\ssh.exe"
    $env:GIT_SSH_VARIANT = "ssh"
    # 5. Fetch Remote Branch
    $fetchOutput = & git fetch $Remote $Branch 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-DeployLog "Git fetch failed ($LASTEXITCODE): $($fetchOutput -join ' ')" "WARNING"
        exit 0 # Non-zero exit may indicate offline network; retry next cycle
    }

    # 6. Check for New Commits
    $localCommit = (& git rev-parse HEAD).Trim()
    $remoteCommit = (& git rev-parse "$Remote/$Branch").Trim()

    if ($localCommit -eq $remoteCommit) {
        # Already up to date, nothing to do
        exit 0
    }

    Write-DeployLog "=========================================================="
    Write-DeployLog "NEW COMMIT DETECTED on $Remote/$Branch!"
    Write-DeployLog "Current Commit : $localCommit"
    Write-DeployLog "Target Commit  : $remoteCommit"
    Write-DeployLog "=========================================================="

    $commitMessage = (& git log -1 --pretty=format:"%s (%an)" "$Remote/$Branch").Trim()
    Write-DeployLog "Deploying: $commitMessage"

    $rollbackCommit = $localCommit

    # 7. Database Safety Backup Before Applying Changes
    # A failed or missing backup aborts deployment immediately — never proceed without a valid snapshot.
    $backupScript = Join-Path $AppRoot "deploy\windows\backup-database.ps1"
    if (-not (Test-Path $backupScript)) {
        Write-DeployLog "CRITICAL: Pre-deployment backup script not found at '$backupScript'! Deployment aborted immediately to protect data integrity." "ERROR"
        throw "Pre-deployment backup script missing at '$backupScript'"
    }

    Write-DeployLog "Taking pre-deployment database safety backup..."
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $backupScript -AppRoot $AppRoot
    $backupExitCode = $LASTEXITCODE
    if ($backupExitCode -ne 0) {
        Write-DeployLog "Pre-deployment backup FAILED (exit code $backupExitCode). Deployment aborted to protect data integrity." "ERROR"
        throw "Backup failed with exit code $backupExitCode"
    }
    Write-DeployLog "Pre-deployment backup completed successfully."

    # 8. Check If Composer Dependencies Changed
    $composerChanged = & git diff --name-only $localCommit $remoteCommit | Where-Object { $_ -eq "composer.json" -or $_ -eq "composer.lock" }

    # 9. Pull Changes Safely (Fast-Forward Only)
    Write-DeployLog "Applying Git updates..."
    $pullOutput = & git merge "$Remote/$Branch" --ff-only 2>&1

    if ($LASTEXITCODE -ne 0) {
        Write-DeployLog "Fast-forward merge failed ($LASTEXITCODE): $($pullOutput -join ' ')" "ERROR"
        Write-DeployLog "Local changes detected that prevent clean fast-forward. Aborting deployment." "ERROR"
        throw "Fast-forward merge failed"
    }

    # 10. Update Composer Dependencies (If changed)
    if ($composerChanged) {
        Write-DeployLog "composer.json / composer.lock changed. Updating dependencies..."
        $composerCmd = Get-Command composer -ErrorAction SilentlyContinue
        if ($composerCmd) {
            $compOut = & composer install --no-dev --optimize-autoloader --no-interaction 2>&1
            Write-DeployLog "Composer install result: $($compOut -join ' ')"
        } elseif (Test-Path "$AppRoot\composer.phar") {
            $compOut = & $PhpExe "$AppRoot\composer.phar" install --no-dev --optimize-autoloader --no-interaction 2>&1
            Write-DeployLog "Composer.phar install result: $($compOut -join ' ')"
        } else {
            Write-DeployLog "Composer executable not found. Skipping composer install." "WARNING"
        }
    }

    # Helper function for safe repository rollback
    function Invoke-GitRollback {
        param ([string]$TargetCommit, [string]$Reason)
        Write-DeployLog "Initiating rollback to commit $TargetCommit (Reason: $Reason)..." "WARNING"

        # 1. Ensure working directory is on $Branch
        $checkoutOut = & git checkout $Branch --force 2>&1
        $checkoutExit = $LASTEXITCODE
        if ($checkoutExit -ne 0) {
            Write-DeployLog "git checkout $Branch returned exit code $checkoutExit : $($checkoutOut -join ' ')" "ERROR"
        }

        # 2. Hard reset branch pointer to the pre-deployment commit
        $resetOut = & git reset --hard $TargetCommit 2>&1
        $resetExit = $LASTEXITCODE
        if ($resetExit -ne 0) {
            Write-DeployLog "git reset --hard $TargetCommit returned exit code $resetExit : $($resetOut -join ' ')" "ERROR"
        } else {
            Write-DeployLog "Git working tree and branch '$Branch' successfully reset to commit $TargetCommit." "INFO"
        }

        # 3. Refresh Laravel caches for restored code
        & $PhpExe artisan config:cache 2>&1 | Out-Null
        & $PhpExe artisan route:cache 2>&1 | Out-Null
        & $PhpExe artisan view:cache 2>&1 | Out-Null

        Write-DeployLog "Rollback of repository code files completed. Branch '$Branch' remains active and usable for future deployments." "WARNING"
        Write-DeployLog "CRITICAL: Git rollback restores code files only, NOT database schema changes." "WARNING"
        Write-DeployLog "The production database was NOT automatically dropped or restored. Database schema restoration requires a deliberate, separately verified recovery procedure." "WARNING"
        Write-DeployLog "If database tables or columns were altered, inspect the schema or restore the verified pre-deployment backup from C:\artsci-backups\daily using verify-backup-restore.ps1 or pg_restore." "WARNING"
    }

    # 11. Run Safe Pending Migrations (NEVER migrate:fresh or reset)
    Write-DeployLog "Checking for pending database migrations..."
    $migOutput = & $PhpExe artisan migrate --force 2>&1
    $migExitCode = $LASTEXITCODE
    Write-DeployLog "Migration output: $($migOutput -join ' ')"

    if ($migExitCode -ne 0) {
        Invoke-GitRollback -TargetCommit $rollbackCommit -Reason "Database migration failed (exit code $migExitCode)"
        throw "Migration failed with exit code $migExitCode"
    }

    # 12. Refresh Laravel Production Caches
    Write-DeployLog "Optimizing Laravel config, route, and view caches..."
    & $PhpExe artisan config:cache | Out-Null
    & $PhpExe artisan route:cache | Out-Null
    & $PhpExe artisan view:cache | Out-Null

    # 13. Application Health Check
    Write-DeployLog "Performing post-deployment health check on '$HealthCheckUrl'..."
    Start-Sleep -Seconds 2

    $healthPassed = $false
    try {
        $response = Invoke-WebRequest -Uri $HealthCheckUrl -UseBasicParsing -TimeoutSec 10 -ErrorAction Stop
        if ($response.StatusCode -eq 200) {
            $healthPassed = $true
        } else {
            Write-DeployLog "Health check returned unexpected HTTP status: $($response.StatusCode)" "WARNING"
        }
    } catch {
        Write-DeployLog "Health check request failed: $_" "WARNING"
    }

    if ($healthPassed) {
        Write-DeployLog "✓ HEALTH CHECK PASSED! Application successfully running commit $remoteCommit." "INFO"
        Write-DeployLog "Deployment completed successfully." "INFO"
    } else {
        Invoke-GitRollback -TargetCommit $rollbackCommit -Reason "Health check failed on $HealthCheckUrl"
        throw "Health check failed; rollback completed"
    }

} finally {
    # Always remove the lockfile upon completion or failure
    Remove-Item $lockFile -Force -ErrorAction SilentlyContinue
}

exit 0

