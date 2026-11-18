# ARTSCI Attendance — Windows 10 Office Server Automation Suite

This directory contains the deployment automation, security scripts, service definitions, IP updater, and automatic GitHub deployment for the ARTSCI Attendance Windows 10 office server.

---

## Server Configuration Summary

* **Project Location**: `C:\ARTSCI\Attendance`
* **PHP Executable**: `C:\PHP85\php.exe` (PHP 8.5)
* **Web Server**: Microsoft IIS 10 on port `8080`
* **Public Domain**: `https://staff-portal.artsci.com.ng`
* **Public Access**: Cloudflare Tunnel (`cloudflared` -> `localhost:8080`)
* **Database**: Local PostgreSQL 18, port `5433`, database `artsci_attendance_import`
* **GitHub Repository**: Connected to `main` branch with auto-deployment

---

## Automation Scripts Overview

| File | Schedule | Purpose |
| :--- | :--- | :--- |
| [`update-office-ip.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/update-office-ip.ps1) | Every 5 min + On Network Change | **Automatic Office Public IP Updater**: Automatically queries public IP services (`api.ipify.org`, `icanhazip.com`, etc.) and updates the approved office network in PostgreSQL whenever the router's public IP changes. Fires every 5 minutes and immediately when a network adapter connects, disconnects, or changes IP/gateway. Protected with mutex concurrency locking and debounce cooldown. |
| [`auto-deploy-github.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/auto-deploy-github.ps1) | Every 3 minutes | **Automatic GitHub Deployment**: Periodically fetches `main` from GitHub, takes a safety DB backup, updates composer/migrations/caches, performs a post-deploy health check (`/up`), and auto-rolls back if an issue occurs. |
| [`setup-deployment-notifications.ps1`](setup-deployment-notifications.ps1) | One-time, per desktop user | Registers an interactive logon task that displays pending deployment notifications outside Session 0. |
| [`deployment-notification.ps1`](deployment-notification.ps1) | On deployment result and user logon | Displays native Windows tray notifications for deployment success and failure. |
| [`setup-windows-tasks.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/setup-windows-tasks.ps1) | One-time Setup | Registers all 4 scheduled tasks in Windows Task Scheduler under `SYSTEM`: Laravel Scheduler (1m), IP Updater (5m + Network Change Event), GitHub Auto-Deploy (3m), DB Backup (daily 02:00). Safely re-runnable. |
| [`backup-database.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/backup-database.ps1) | Daily 02:00 AM | Automated PostgreSQL `pg_dump -F c` backup with 30-day retention pruning and off-disk replication to secondary storage (`D:\artsci-backups`). |
| [`verify-backup-restore.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/verify-backup-restore.ps1) | Manual / Test | Restores backup into an isolated test database (`artsci_test_verification`) and verifies record counts without touching production data. |
| [`install-prerequisites.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/install-prerequisites.ps1) | One-time Setup | Hardware audit (RAM, disk, CPU), network adapter check, and sleep/hibernate disabling via `powercfg`. |
| [`setup-artsci.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/setup-artsci.ps1) | One-time Setup | Initial app setup, least-privilege ICACLS permissions on `storage`, and production cache warming. |
| [`configure-firewall.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/configure-firewall.ps1) | One-time Setup | Configures Windows Defender Firewall to allow LAN subnets and block external access to PostgreSQL. |
| [`.env.office.example`](file:///home/codecps/Desktop/attendance/deploy/windows/.env.office.example) | Template | Production `.env` template configured for PostgreSQL port 5433, database `artsci_attendance_import`, and `hybrid` verification mode. |

---

## Task 1: How the Automatic Office Public IP Updater Works

1. **Dual Trigger Execution**:
   - **Periodic Schedule**: Runs every 5 minutes indefinitely.
   - **Immediate Event Trigger**: Windows Task Scheduler monitors the `Microsoft-Windows-NetworkProfile/Operational` event log channel for Event IDs `10000` (Network connected), `10001` (Network disconnected), and `10002` (Network profile changed). Whenever an ethernet cable is plugged in, Wi-Fi connects, or the router restarts and reassigns an IP, the updater runs immediately after a 3-second link stabilization delay.
2. **Concurrency & Debounce Protection**:
   - Uses file mutex `storage\framework\update-office-ip.lock` with a 3-minute stale timeout to ensure overlapping executions never occur.
   - Task Scheduler settings enforce `MultipleInstancesPolicy: IgnoreNew`.
   - Debounce mechanism (`storage\framework\update-office-ip-lastsuccess.txt`) suppresses redundant executions if a successful check already occurred within the last 15 seconds, preventing external API rate-limiting during network flapping.
3. **Redundant Providers & SSL Integrity**:
   - The `PublicIpService` queries a redundant pool of public IP resolvers (`api.ipify.org`, `icanhazip.com`, `checkip.amazonaws.com`, `ifconfig.me`, `ipinfo.io`) with strict validation rejecting private/loopback addresses.
   - Full SSL certificate verification is preserved throughout.
4. **Smart Database Update**:
   - If the public IP matches the currently approved IP, it updates timestamps without creating audit noise.
   - If the router was assigned a new public IP (e.g. after a router restart or power outage), it automatically updates the designated `AttendanceNetwork` record (`<new_ip>/32`) and logs the change in `attendance_audit_logs`.
   - If offline (e.g. cable unplugged), it records failure in settings but **leaves the approved network untouched**, so staff on that network can resume checking in as soon as connectivity restores.
5. **Cloudflare Tunnel Visitor Matching**:
   - Staff phones connect to `https://staff-portal.artsci.com.ng`.
   - Cloudflare identifies the connecting IP of staff on office Wi-Fi as the office's public IP.
   - Cloudflare forwards this in `CF-Connecting-IP`.
   - Laravel validates that the visitor IP matches the office public IP, approving check-in!
   - Staff at home or on mobile data have different public IPs and are blocked (403 Forbidden).
6. **No Router Access Required**: Does not require router logins, port forwarding, or DDNS credentials.

---

## Task 2: How GitHub Auto-Deployment Works

1. **Scheduled Polling**: Every 3 minutes, `auto-deploy-github.ps1` runs in the background.
2. **Concurrency Locking**: Uses `storage\framework\deploy.lock` to prevent overlapping deployments.
3. **Change Detection**: Runs `git fetch origin main` and compares local `HEAD` vs `origin/main`. If there are no new commits, it exits immediately.
4. **Safety Backup**: If new commits are detected, it executes `backup-database.ps1` to take a snapshot before applying changes.
5. **Fast-Forward Merge**: Runs `git merge origin/main --ff-only` to ensure server `.env`, local database, and server secrets are never overwritten.
6. **Composer & Migrations**:
   - If `composer.json` or `composer.lock` changed, runs `composer install --no-dev --optimize-autoloader`.
   - Runs safe pending migrations via `php artisan migrate --force`. (Never runs `migrate:fresh`).
7. **Cache Refresh**: Compiles production configs, routes, and views (`config:cache`, `route:cache`, `view:cache`).
8. **Health Check & Auto-Rollback**:
   - Sends a request to `http://127.0.0.1:8080/up`.
   - If HTTP 200 is received, deployment succeeds.
   - If the health check fails, it automatically rolls back `git checkout` to the previous commit and restores caches, keeping the website online.
9. **Private Deployment Journal**:
   - Deployment start, stage changes, success, and failure are appended as structured JSON lines to `storage\app\private\deployment-events.jsonl`.
   - The file is outside `public` and is read by Laravel through the admin-only Deployment Monitor.
   - The SYSTEM task records a final success only after the existing post-deployment health check returns HTTP 200.
   - If no user is logged in, the record remains in the journal and is picked up by that user's notification task at the next sign-in.

## Deployment Notifications and Monitor

The auto-deploy task runs as SYSTEM, so it must not show UI directly. Install the notification task while signed in as the Windows desktop user who should receive notices. This task uses that user's interactive logon token and a built-in Windows Forms tray notification; it does not install third-party packages.

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
cd C:\ARTSCI\Attendance
.\deploy\windows\setup-deployment-notifications.ps1 -AppRoot C:\ARTSCI\Attendance
```

The script registers only `ARTSCI-Deployment-Notification` for the current user. It does not replace the SYSTEM deployment task or alter server configuration. Run it for the designated desktop account; it refuses to replace a task registered for a different account.

Test a notification in the currently signed-in desktop session:

```powershell
& "$env:SystemRoot\System32\WindowsPowerShell\v1.0\powershell.exe" `
  -NoProfile -ExecutionPolicy Bypass `
  -File "C:\ARTSCI\Attendance\deploy\windows\deployment-notification.ps1" `
  -AppRoot "C:\ARTSCI\Attendance" -Test
Get-ScheduledTask -TaskName "ARTSCI-Deployment-Notification"
```

The signed-in user is notified for each terminal deployment event once. Delivery IDs are stored in that user's `%LOCALAPPDATA%\ARTSCI\Attendance` directory; undelivered server events remain in the private journal until the notification task can read them. Review current status and diagnostic stages at `/admin/deployments` while signed in as an authorized administrator. The dashboard refreshes its data every 15 seconds; it never serves raw deployment log files.

Run the application-level monitor tests from the project root without deploying or running migrations:

```powershell
php artisan test --filter=DeploymentMonitorTest
php artisan route:list --name=admin.deployments
```

---

## One-Time Server Setup Instructions

Open PowerShell as **Administrator** on the Windows 10 server:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass

# 1. Register all scheduled tasks (Scheduler, IP Updater, Auto-Deploy, Daily Backup)
cd C:\ARTSCI\Attendance
.\deploy\windows\setup-windows-tasks.ps1
```

To configure GitHub authentication for automatic pulling:
* **Option A (SSH Deploy Key - Recommended)**:
  Generate an SSH key: `ssh-keygen -t ed25519 -C "artsci-office-server"`
  Add the public key to GitHub repository -> **Settings** -> **Deploy Keys** (read-only).
  Set git remote: `git remote set-url origin git@github.com:DjCeejay/attendance.git`
* **Option B (GitHub Personal Access Token)**:
  Generate a fine-grained or classic GitHub PAT with `Contents: Read-only`.
  Configure Git Credential Manager or set: `git remote set-url origin https://<TOKEN>@github.com/DjCeejay/attendance.git`

---

## Verifying the Network-Change Trigger on Windows

After running `.\deploy\windows\setup-windows-tasks.ps1`:

1. **Verify Task Triggers in Task Scheduler GUI**:
   - Press `Win + R`, type `taskschd.msc`, and hit Enter.
   - Click **Task Scheduler Library** and double-click `ARTSCI-Office-IP-Updater`.
   - Under the **Triggers** tab, verify both triggers are present:
     - `Daily / Repeat every 5 minutes`
     - `On an event - Log: Microsoft-Windows-NetworkProfile/Operational, Event ID: 10000, 10001, 10002`
   - Under the **Settings** tab, verify **If the task is already running**: `Do not start a new instance`.

2. **Verify Registration via PowerShell**:
   ```powershell
   Get-ScheduledTask -TaskName "ARTSCI-Office-IP-Updater" | Select-Object TaskName, State
   (Get-ScheduledTask -TaskName "ARTSCI-Office-IP-Updater").Triggers
   ```

3. **Live Network Change Verification**:
   - Disconnect and reconnect the server's Ethernet cable or toggle Wi-Fi.
   - Wait 5 seconds.
   - Inspect the updater log:
     ```powershell
     Get-Content C:\ARTSCI\Attendance\storage\logs\office-ip-updater.log -Tail 20
     ```
   - Confirm that the updater executed automatically upon connection change without overlapping.
