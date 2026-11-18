param (
    [Parameter(Mandatory = $true)]
    [string]$AppRoot,
    [switch]$Test
)

$ErrorActionPreference = "Stop"

Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
Add-Type -TypeDefinition @'
using System;
using System.IO;

public sealed class BoundedReadStream : Stream
{
    private readonly Stream inner;
    private long remaining;

    public BoundedReadStream(Stream inner, long length)
    {
        this.inner = inner;
        remaining = length;
    }

    public override bool CanRead { get { return true; } }
    public override bool CanSeek { get { return false; } }
    public override bool CanWrite { get { return false; } }
    public override long Length { get { throw new NotSupportedException(); } }
    public override long Position
    {
        get { throw new NotSupportedException(); }
        set { throw new NotSupportedException(); }
    }

    public override void Flush() { }

    public override int Read(byte[] buffer, int offset, int count)
    {
        if (remaining <= 0)
        {
            return 0;
        }

        if (count > remaining)
        {
            count = (int)remaining;
        }

        int read = inner.Read(buffer, offset, count);
        remaining -= read;
        return read;
    }

    public override long Seek(long offset, SeekOrigin origin)
    {
        throw new NotSupportedException();
    }

    public override void SetLength(long value)
    {
        throw new NotSupportedException();
    }

    public override void Write(byte[] buffer, int offset, int count)
    {
        throw new NotSupportedException();
    }

    protected override void Dispose(bool disposing)
    {
        if (disposing)
        {
            inner.Dispose();
        }

        base.Dispose(disposing);
    }
}
'@

function Show-DeploymentNotification {
    param (
        [string]$Title,
        [string]$Message,
        [System.Windows.Forms.ToolTipIcon]$Icon = [System.Windows.Forms.ToolTipIcon]::Info
    )

    $notifyIcon = New-Object System.Windows.Forms.NotifyIcon
    $script:notificationShown = $false
    $script:notificationClosed = $false

    try {
        $notifyIcon.Icon = if ($Icon -eq [System.Windows.Forms.ToolTipIcon]::Error) {
            [System.Drawing.SystemIcons]::Error
        } else {
            [System.Drawing.SystemIcons]::Information
        }
        $notifyIcon.add_BalloonTipShown({
            $script:notificationShown = $true
        })
        $notifyIcon.add_BalloonTipClosed({
            $script:notificationClosed = $true
        })

        $notifyIcon.Visible = $true
        $notifyIcon.BalloonTipTitle = $Title
        $notifyIcon.BalloonTipText = $Message
        $notifyIcon.BalloonTipIcon = $Icon
        $notifyIcon.ShowBalloonTip(8000)

        $stopwatch = [System.Diagnostics.Stopwatch]::StartNew()
        while (-not $script:notificationShown -and -not $script:notificationClosed -and $stopwatch.Elapsed.TotalSeconds -lt 10) {
            [System.Windows.Forms.Application]::DoEvents()
            Start-Sleep -Milliseconds 50
        }
        $stopwatch.Stop()

        return [bool]$script:notificationShown
    } catch {
        Write-NotificationLog "Desktop notification could not be displayed: $($_.Exception.Message)"
        return $false
    } finally {
        $notifyIcon.Visible = $false
        $notifyIcon.Dispose()
    }
}

$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$sessionId = [Diagnostics.Process]::GetCurrentProcess().SessionId
if (-not $env:LOCALAPPDATA -or $identity.User.Value -eq "S-1-5-18" -or $sessionId -eq 0) {
    Write-Error "The notification helper must run in a logged-in interactive user session, not as SYSTEM or in Session 0."
    exit 1
}

if (-not (Test-Path -LiteralPath (Join-Path $AppRoot "artisan") -PathType Leaf)) {
    Write-Error "Laravel application root not found: $AppRoot"
    exit 1
}

$stateDirectory = Join-Path $env:LOCALAPPDATA "ARTSCI\Attendance"
$statePath = Join-Path $stateDirectory "deployment-notification-state.json"
$notificationLog = Join-Path $stateDirectory "deployment-notifications.log"
$recoveryMarkerPath = "$statePath.recovery-required"
New-Item -ItemType Directory -Path $stateDirectory -Force | Out-Null

function Write-NotificationLog {
    param ([string]$Message)
    try {
        Add-Content -LiteralPath $notificationLog -Value "$(Get-Date -Format 'o') $Message" -Encoding UTF8
    } catch {
        # Logging is best-effort and must not prevent notification processing.
    }
}

$mutexAcquired = $false
$mutex = $null

try {
    $mutexName = "Local\ARTSCI.Attendance.DeploymentNotifications.$($identity.User.Value)"
    $mutex = [System.Threading.Mutex]::new($false, $mutexName)

    try {
        $mutexAcquired = $mutex.WaitOne(0)
    } catch [System.Threading.AbandonedMutexException] {
        $mutexAcquired = $true
    } catch {
        Write-NotificationLog "Could not acquire deployment notification mutex: $($_.Exception.Message)"
        exit 1
    }

    if (-not $mutexAcquired) {
        exit 0
    }

    if ($Test) {
        if (-not (Show-DeploymentNotification `
            -Title "ARTSCI Attendance deployment notification test" `
            -Message "Windows desktop notifications are working for this user session." `
            -Icon ([System.Windows.Forms.ToolTipIcon]::Info))) {
            Write-Error "The test notification could not be confirmed as shown."
            exit 1
        }
        exit 0
    }

    $historyPath = Join-Path $AppRoot "storage\app\private\deployment-events.jsonl"
    if (-not (Test-Path -LiteralPath $historyPath -PathType Leaf)) {
        exit 0
    }

    if (Test-Path -LiteralPath $recoveryMarkerPath -PathType Leaf) {
        Write-NotificationLog "Delivery state recovery is required. Historical notifications will remain pending until the recovery marker is reviewed and removed."
        exit 0
    }

    function Save-DeliveryState {
        param (
            [System.Collections.Hashtable]$Candidate,
            [string]$StatePath,
            [string]$LogPath
        )

        $temporaryStatePath = "$StatePath.tmp"
        try {
            if (Test-Path -LiteralPath $temporaryStatePath) {
                Remove-Item -LiteralPath $temporaryStatePath -Force -ErrorAction Stop
            }

            $stateJson = @{
                delivered_ids = @($Candidate.Keys)
            } | ConvertTo-Json -Depth 3
            [System.IO.File]::WriteAllText(
                $temporaryStatePath,
                $stateJson,
                [System.Text.UTF8Encoding]::new($false)
            )

            Move-Item -LiteralPath $temporaryStatePath -Destination $StatePath -Force -ErrorAction Stop
            return $true
        } catch {
            try {
                Add-Content -LiteralPath $LogPath -Value "$(Get-Date -Format 'o') Delivery state could not be persisted: $($_.Exception.Message)" -Encoding UTF8
            } catch {}

            try {
                if (Test-Path -LiteralPath $temporaryStatePath) {
                    Remove-Item -LiteralPath $temporaryStatePath -Force -ErrorAction SilentlyContinue
                }
            } catch {}
            return $false
        }
    }

    $delivered = @{}
    if (Test-Path -LiteralPath $statePath -PathType Leaf) {
        $stateJson = $null
        $state = $null
        try {
            $stateJson = [System.IO.File]::ReadAllText($statePath, [System.Text.Encoding]::UTF8)
            if ([string]::IsNullOrWhiteSpace($stateJson)) {
                throw [System.InvalidOperationException]::new("Delivery state file is empty.")
            }
            $state = ConvertFrom-Json -InputObject $stateJson -ErrorAction Stop

            if (-not ($state.PSObject.Properties.Name -contains "delivered_ids")) {
                throw [System.InvalidOperationException]::new("Delivery state is missing a delivered_ids array.")
            }
            if ($null -eq $state.delivered_ids -or -not ($state.delivered_ids -is [System.Array])) {
                throw [System.InvalidOperationException]::new("Delivery state delivered_ids value is not an array.")
            }

            foreach ($id in @($state.delivered_ids)) {
                if ($id -is [string] -and $id.Length -gt 0) {
                    $delivered[$id] = $true
                }
            }
        } catch {
            Write-NotificationLog "Delivery state is corrupt or unreadable: $($_.Exception.Message)"

            $corruptPath = $statePath + ".corrupt-" + (Get-Date -Format "yyyyMMdd-HHmmss-fff")
            try {
                Move-Item -LiteralPath $statePath -Destination $corruptPath -Force
                Write-NotificationLog "Preserved corrupt delivery state at $corruptPath."
            } catch {
                Write-NotificationLog "Could not preserve corrupt delivery state at $corruptPath: $($_.Exception.Message)"
                exit 1
            }

            try {
                [System.IO.File]::WriteAllText(
                    $recoveryMarkerPath,
                    "Created $(Get-Date -Format 'o') after delivery state corruption." + [Environment]::NewLine,
                    [System.Text.UTF8Encoding]::new($false)
                )
                Write-NotificationLog "Delivery state recovery is required; historical notifications are blocked until the marker is reviewed."
            } catch {
                Write-NotificationLog "Could not create delivery state recovery marker: $($_.Exception.Message)"
                exit 1
            }

            exit 0
        }
    } else {
        # A missing state file is ambiguous: bootstrapping existing terminal IDs as
        # delivered prevents old history from replaying after state loss.
        $existingTerminalIds = @{}
        $bootstrapFileStream = $null
        $boundedBootstrapStream = $null
        $bootstrapReader = $null
        try {
            $bootstrapFileStream = [System.IO.File]::Open(
                $historyPath,
                [System.IO.FileMode]::Open,
                [System.IO.FileAccess]::Read,
                [System.IO.FileShare]::ReadWrite
            )

            $snapshotLength = $bootstrapFileStream.Length
            $snapshotEndsWithNewline = $false
            if ($snapshotLength -gt 0) {
                $bootstrapFileStream.Seek($snapshotLength - 1, [System.IO.SeekOrigin]::Begin)
                $snapshotEndsWithNewline = ($bootstrapFileStream.ReadByte() -eq 10)
                $bootstrapFileStream.Seek(0, [System.IO.SeekOrigin]::Begin)
            }

            $boundedBootstrapStream = [BoundedReadStream]::new($bootstrapFileStream, $snapshotLength)
            $bootstrapFileStream = $null
            $bootstrapReader = [System.IO.StreamReader]::new(
                $boundedBootstrapStream,
                [System.Text.Encoding]::UTF8,
                $true
            )
            $boundedBootstrapStream = $null

            $pendingBootstrapLine = $null
            $hasPendingBootstrapLine = $false
            while (($bootstrapLine = $bootstrapReader.ReadLine()) -ne $null) {
                if ($hasPendingBootstrapLine) {
                    if (-not [string]::IsNullOrWhiteSpace($pendingBootstrapLine)) {
                        try {
                            $bootstrapEvent = ConvertFrom-Json -InputObject $pendingBootstrapLine -ErrorAction Stop
                            if ($bootstrapEvent.deployment_id -and $bootstrapEvent.status -in @("succeeded", "failed")) {
                                $existingTerminalIds[[string]$bootstrapEvent.deployment_id] = $true
                            }
                        } catch {
                        }
                    }
                }

                $pendingBootstrapLine = $bootstrapLine
                $hasPendingBootstrapLine = $true
            }

            if ($hasPendingBootstrapLine -and $snapshotEndsWithNewline -and
                -not [string]::IsNullOrWhiteSpace($pendingBootstrapLine)) {
                try {
                    $bootstrapEvent = ConvertFrom-Json -InputObject $pendingBootstrapLine -ErrorAction Stop
                    if ($bootstrapEvent.deployment_id -and $bootstrapEvent.status -in @("succeeded", "failed")) {
                        $existingTerminalIds[[string]$bootstrapEvent.deployment_id] = $true
                    }
                } catch {
                }
            }
        } catch {
            Write-NotificationLog "Could not bootstrap missing delivery state: $($_.Exception.Message)"
            exit 1
        } finally {
            if ($bootstrapReader) {
                $bootstrapReader.Dispose()
            } elseif ($boundedBootstrapStream) {
                $boundedBootstrapStream.Dispose()
            } elseif ($bootstrapFileStream) {
                $bootstrapFileStream.Dispose()
            }
        }

        if (-not (Save-DeliveryState -Candidate $existingTerminalIds -StatePath $statePath -LogPath $notificationLog)) {
            Write-NotificationLog "Missing delivery state could not be bootstrapped; notifications remain blocked to prevent replay."
            exit 1
        }

        Write-NotificationLog "Missing delivery state was bootstrapped with $($existingTerminalIds.Count) existing terminal deployment IDs; historical notifications are not replayed."
        exit 0
    }

    $latestTerminalEvents = @{}
    $journalStream = $null
    $reader = $null
    try {
        $journalStream = [System.IO.File]::Open(
            $historyPath,
            [System.IO.FileMode]::Open,
            [System.IO.FileAccess]::Read,
            [System.IO.FileShare]::ReadWrite
        )
        $reader = [System.IO.StreamReader]::new(
            $journalStream,
            [System.Text.Encoding]::UTF8,
            $true
        )
        $journalStream = $null
        $lineNumber = 0
        while (($line = $reader.ReadLine()) -ne $null) {
            $lineNumber++
            if ([string]::IsNullOrWhiteSpace($line)) {
                continue
            }

            try {
                $event = ConvertFrom-Json -InputObject $line -ErrorAction Stop
            } catch {
                Write-NotificationLog "Skipped malformed deployment journal record at line $lineNumber."
                continue
            }

            if (-not $event.deployment_id) {
                Write-NotificationLog "Skipped deployment journal record without an ID at line $lineNumber."
                continue
            }

            if ($event.status -notin @("succeeded", "failed")) {
                continue
            }

            $eventTime = [DateTimeOffset]::MinValue
            if (-not [DateTimeOffset]::TryParse([string]$event.timestamp, [ref]$eventTime)) {
                Write-NotificationLog "Skipped terminal deployment record with an invalid timestamp at line $lineNumber."
                continue
            }

            $existing = $latestTerminalEvents[[string]$event.deployment_id]
            if (-not $existing -or $eventTime -ge $existing.eventTime) {
                $latestTerminalEvents[[string]$event.deployment_id] = @{
                    record = $event
                    eventTime = $eventTime
                }
            }
        }
    } catch {
        Write-NotificationLog "Deployment journal could not be read: $($_.Exception.Message)"
        exit 1
    } finally {
        if ($reader) {
            $reader.Dispose()
        } elseif ($journalStream) {
            $journalStream.Dispose()
        }
    }

    foreach ($entry in @($latestTerminalEvents.Values | Sort-Object { $_.eventTime })) {
        $event = $entry.record
        $id = [string]$event.deployment_id
        if ($delivered.ContainsKey($id)) {
            continue
        }

        $commit = [string]$event.commit
        if ($commit.Length -gt 12) {
            $commit = $commit.Substring(0, 12)
        }

        if ($event.status -eq "succeeded") {
            $title = "ARTSCI Attendance deployment succeeded"
            $detail = [string]$event.message
            $icon = [System.Windows.Forms.ToolTipIcon]::Info
        } else {
            $title = "ARTSCI Attendance deployment failed"
            $detail = "Failed during $($event.stage). $([string]$event.error)"
            $icon = [System.Windows.Forms.ToolTipIcon]::Error
        }

        $message = "Commit $commit - $detail"
        if ($message.Length -gt 240) {
            $message = $message.Substring(0, 237) + "..."
        }

        if (-not (Show-DeploymentNotification -Title $title -Message $message -Icon $icon)) {
            Write-NotificationLog "Notification for deployment $id could not be confirmed; it remains pending."
            continue
        }

        $candidate = @{}
        foreach ($deliveredId in $delivered.Keys) {
            $candidate[$deliveredId] = $true
        }
        $candidate[$id] = $true

        if (-not (Save-DeliveryState -Candidate $candidate -StatePath $statePath -LogPath $notificationLog)) {
            Write-NotificationLog "Notification for deployment $id was shown but delivery state could not be persisted; it may be repeated on the next run."
            continue
        }

        $delivered = $candidate
    }
} finally {
    if ($mutex) {
        try {
            if ($mutexAcquired) {
                $mutex.ReleaseMutex()
            }
        } catch {
            # Mutex ownership may already be absent.
        } finally {
            $mutex.Dispose()
        }
    }
}
