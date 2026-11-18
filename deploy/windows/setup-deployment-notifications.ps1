param (
    [string]$AppRoot = "C:\ARTSCI\Attendance"
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path -LiteralPath (Join-Path $AppRoot "artisan"))) {
    $scriptParent = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
    if (Test-Path -LiteralPath (Join-Path $scriptParent "artisan")) {
        $AppRoot = $scriptParent
    } else {
        throw "Could not locate the Laravel application. Pass its path using -AppRoot."
    }
}

$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
if ($identity.User.Value -eq "S-1-5-18") {
    throw "Run this setup script as the logged-in desktop user, not as SYSTEM."
}
if ([Diagnostics.Process]::GetCurrentProcess().SessionId -eq 0) {
    throw "Run this setup script from an interactive Windows desktop session, not Session 0."
}

$helperPath = Join-Path $AppRoot "deploy\windows\deployment-notification.ps1"
if (-not (Test-Path -LiteralPath $helperPath -PathType Leaf)) {
    throw "Notification helper not found: $helperPath"
}

$historyDirectory = Join-Path $AppRoot "storage\app\private"
New-Item -ItemType Directory -Path $historyDirectory -Force | Out-Null

$taskName = "ARTSCI-Deployment-Notification"
$userName = $identity.Name
$existingTask = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existingTask) {
    $existingUser = [string]$existingTask.Principal.UserId
    try {
        $existingUserSid = (New-Object System.Security.Principal.NTAccount($existingUser)).Translate([System.Security.Principal.SecurityIdentifier]).Value
    } catch {
        $existingUserSid = $existingUser
    }
    if ($existingUserSid -ne $identity.User.Value) {
        throw "Task [$taskName] is already registered for another Windows user ($existingUser)."
    }
}

$powershellExe = Join-Path $PSHOME "powershell.exe"
$arguments = "-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$helperPath`" -AppRoot `"$AppRoot`""
$action = New-ScheduledTaskAction `
    -Execute $powershellExe `
    -Argument $arguments `
    -WorkingDirectory $AppRoot
$trigger = New-ScheduledTaskTrigger -AtLogOn -User $userName
$principal = New-ScheduledTaskPrincipal `
    -UserId $userName `
    -LogonType Interactive `
    -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet `
    -StartWhenAvailable `
    -MultipleInstances Queue `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 5)

Register-ScheduledTask `
    -TaskName $taskName `
    -Action $action `
    -Trigger $trigger `
    -Principal $principal `
    -Settings $settings `
    -Description "Displays queued ARTSCI Attendance deployment events in this user's interactive Windows session." `
    -Force | Out-Null

Write-Host "Registered [$taskName] for $userName."
Write-Host "The task runs at sign-in; the deployment script also triggers it after each result."
Write-Host "To test a desktop notification, run:"
Write-Host "  & `"$powershellExe`" -NoProfile -ExecutionPolicy Bypass -File `"$helperPath`" -AppRoot `"$AppRoot`" -Test"
