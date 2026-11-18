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
    [string]$HealthCheckUrl = "http://127.0.0.1:8080/up",
    [string]$NotificationTaskName = "\ARTSCI-Deployment-Notification"
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
$script:deploymentHistoryFile = Join-Path $AppRoot "storage\app\private\deployment-events.jsonl"
$script:notificationTaskName = $NotificationTaskName
$script:deploymentId = $null
$script:deploymentCommit = $null
$script:deploymentStage = "preflight"

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

function Write-DeploymentEvent {
    param (
        [ValidateSet("running", "succeeded", "failed")]
        [string]$Status,
        [string]$Stage,
        [string]$Message,
        [string]$ErrorMessage
    )

    $stream = $null

    try {
        if (-not $script:deploymentId) {
            return
        }

        $historyDirectory = Split-Path $script:deploymentHistoryFile -Parent
        if (-not (Test-Path -LiteralPath $historyDirectory)) {
            New-Item -ItemType Directory -Path $historyDirectory -Force | Out-Null
        }

        $record = [ordered]@{
            deployment_id = $script:deploymentId
            timestamp = (Get-Date).ToUniversalTime().ToString("o")
            status = $Status
            stage = $Stage
            commit = $script:deploymentCommit
            message = $Message
            error = $ErrorMessage
        }
        $line = (ConvertTo-Json -InputObject $record -Compress -Depth 4) + "`n"
        $encoding = [System.Text.UTF8Encoding]::new($false)
        $bytes = $encoding.GetBytes($line)
        $stream = [System.IO.File]::Open(
            $script:deploymentHistoryFile,
            [System.IO.FileMode]::Append,
            [System.IO.FileAccess]::Write,
            [System.IO.FileShare]::ReadWrite
        )

        $stream.Write($bytes, 0, $bytes.Length)
        $stream.Flush()
    } catch {
        try {
            Write-DeployLog "Deployment journal event could not be recorded: $($_.Exception.Message)" "WARNING"
        } catch {
            # The deployment journal is advisory; warning-log failures must not affect deployment.
        }
    } finally {
        if ($stream) {
            try {
                $stream.Dispose()
            } catch {
                try {
                    Write-DeployLog "Deployment journal file stream could not be closed cleanly: $($_.Exception.Message)" "WARNING"
                } catch {
                    # The deployment journal is advisory; warning-log failures must not affect deployment.
                }
            }
        }
    }
}

function Set-DeploymentStage {
    param (
        [string]$Stage,
        [string]$Message
    )

    $script:deploymentStage = $Stage
    Write-DeployLog $Message
    Write-DeploymentEvent -Status "running" -Stage $Stage -Message $Message
}

function Request-DeploymentNotification {
    try {
        $taskScheduler = Join-Path $env:SystemRoot "System32\schtasks.exe"
        & $taskScheduler /Run /TN $script:notificationTaskName 2>&1 | Out-Null
        if ($LASTEXITCODE -ne 0) {
            Write-DeployLog "Notification task '$script:notificationTaskName' could not be started (exit code $LASTEXITCODE). The event remains in the private deployment journal." "WARNING"
        }
    } catch {
        Write-DeployLog "Notification task '$script:notificationTaskName' could not be requested: $($_.Exception.Message)" "WARNING"
    }
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

    $script:deploymentId = [guid]::NewGuid().ToString("N")
    $script:deploymentCommit = $remoteCommit
    Set-DeploymentStage -Stage "detected" -Message "Deployment started for commit $remoteCommit."

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

    Set-DeploymentStage -Stage "backup" -Message "Taking pre-deployment database safety backup..."
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $backupScript -AppRoot $AppRoot
    $backupExitCode = $LASTEXITCODE
    if ($backupExitCode -ne 0) {
        Write-DeployLog "Pre-deployment backup FAILED (exit code $backupExitCode). Deployment aborted to protect data integrity." "ERROR"
        throw "Backup failed with exit code $backupExitCode"
    }
    Set-DeploymentStage -Stage "backup_completed" -Message "Pre-deployment backup completed successfully."

    # 8. Check If Composer Dependencies Changed
    $composerChanged = & git diff --name-only $localCommit $remoteCommit | Where-Object { $_ -eq "composer.json" -or $_ -eq "composer.lock" }

    # 9. Pull Changes Safely (Fast-Forward Only)
    Set-DeploymentStage -Stage "git_merge" -Message "Applying Git updates with a fast-forward-only merge."
    $pullOutput = & git merge "$Remote/$Branch" --ff-only 2>&1

    if ($LASTEXITCODE -ne 0) {
        Write-DeployLog "Fast-forward merge failed ($LASTEXITCODE): $($pullOutput -join ' ')" "ERROR"
        Write-DeployLog "Local changes detected that prevent clean fast-forward. Aborting deployment." "ERROR"
        throw "Fast-forward merge failed"
    }

    # 10. Update Composer Dependencies (If changed)
    if ($composerChanged) {
        Set-DeploymentStage -Stage "composer" -Message "composer.json / composer.lock changed. Updating dependencies..."
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
        Write-DeploymentEvent -Status "running" -Stage "rollback" -Message "Repository rollback started." -ErrorMessage $Reason

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
    Set-DeploymentStage -Stage "migrations" -Message "Checking for pending database migrations."
    $migOutput = & $PhpExe artisan migrate --force 2>&1
    $migExitCode = $LASTEXITCODE
    Write-DeployLog "Migration output: $($migOutput -join ' ')"

    if ($migExitCode -ne 0) {
        Invoke-GitRollback -TargetCommit $rollbackCommit -Reason "Database migration failed (exit code $migExitCode)"
        throw "Migration failed with exit code $migExitCode"
    }

    # 12. Refresh Laravel Production Caches
    Set-DeploymentStage -Stage "cache_refresh" -Message "Optimizing Laravel config, route, and view caches."
    & $PhpExe artisan config:cache | Out-Null
    & $PhpExe artisan route:cache | Out-Null
    & $PhpExe artisan view:cache | Out-Null

    # 13. Application Health Check
    Set-DeploymentStage -Stage "health_check" -Message "Performing post-deployment health check on '$HealthCheckUrl'."
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
        $script:deploymentStage = "completed"
        Write-DeploymentEvent -Status "succeeded" -Stage "completed" -Message "Deployment completed and health check passed."
        Request-DeploymentNotification
    } else {
        Invoke-GitRollback -TargetCommit $rollbackCommit -Reason "Health check failed on $HealthCheckUrl"
        throw "Health check failed; rollback completed"
    }

} catch {
    $deploymentError = $_.Exception.Message
    if ($script:deploymentId) {
        Write-DeployLog "Deployment failed during '$script:deploymentStage': $deploymentError" "ERROR"
        try {
            Write-DeploymentEvent `
                -Status "failed" `
                -Stage $script:deploymentStage `
                -Message "Deployment failed during $script:deploymentStage." `
                -ErrorMessage $deploymentError
            Request-DeploymentNotification
        } catch {
            Write-DeployLog "Could not persist or dispatch the deployment failure event: $($_.Exception.Message)" "ERROR"
        }
    }
    throw

} finally {
    # Always remove the lockfile upon completion or failure
    Remove-Item $lockFile -Force -ErrorAction SilentlyContinue
}

exit 0

