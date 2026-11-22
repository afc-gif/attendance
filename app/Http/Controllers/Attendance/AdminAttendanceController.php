<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceAuditLog;
use App\Models\AttendanceCredential;
use App\Models\AttendanceNetwork;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Passkeys\Passkey;

class AdminAttendanceController extends Controller
{
    protected function authorizeAdmin(): void
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Unauthorized. Attendance management is restricted to authorized administrators.');
        }
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin();
        $this->autoCheckoutForgottenRecords();

        $tz = AttendanceSetting::get('timezone', config('app.timezone', 'UTC'));
        $selectedDate = $request->input('date', Carbon::today($tz)->toDateString());
        $date = Carbon::parse($selectedDate, $tz);

        $selectedUser = $request->input('user_id');
        $selectedStatus = $request->input('status');

        $users = User::orderBy('name')->get();
        $totalStaffCount = $users->count();

        // Pending Approval Lists
        $pendingUsers = User::where('status', 'pending')->orderBy('created_at', 'desc')->get();
        $pendingCredentials = AttendanceCredential::with('user')
            ->where('approval_status', 'pending')
            ->orderBy('registered_at', 'desc')
            ->get();

        $query = AttendanceRecord::with(['user', 'checkInCredential'])
            ->whereDate('attendance_date', $date);

        if ($selectedUser) {
            $query->where('user_id', $selectedUser);
        }

        if ($selectedStatus) {
            $query->where('status', $selectedStatus);
        }

        $records = $query->orderBy('check_in_at', 'desc')->paginate(20)->appends($request->query());

        // Overview Summary Metrics for selected date
        $allDateRecords = AttendanceRecord::whereDate('attendance_date', $date)->get();

        $presentCount = $allDateRecords->whereNotNull('check_in_at')->count();
        $lateCount = $allDateRecords->where('status', 'late')->count();
        $checkedOutCount = $allDateRecords->whereNotNull('check_out_at')->count();
        $currentlyPresentCount = $allDateRecords->filter(fn ($r) => $r->isCheckedIn())->count();
        $notCheckedInCount = max(0, $totalStaffCount - $presentCount);

        return view('admin.attendance.index', compact(
            'date',
            'selectedDate',
            'selectedUser',
            'selectedStatus',
            'users',
            'records',
            'totalStaffCount',
            'presentCount',
            'lateCount',
            'checkedOutCount',
            'currentlyPresentCount',
            'notCheckedInCount',
            'pendingUsers',
            'pendingCredentials'
        ));
    }

    public function approveUser(User $user)
    {
        $this->authorizeAdmin();

        $user->update(['status' => 'approved']);

        AttendanceAuditLog::logEvent(
            eventType: 'user_account_approval',
            actor: Auth::user(),
            affectedUser: $user,
            reason: 'Approved pending staff account'
        );

        return back()->with('success', "Staff account for {$user->name} has been approved.");
    }

    public function rejectUser(User $user)
    {
        $this->authorizeAdmin();

        $userName = $user->name;
        $user->update(['status' => 'rejected']);

        AttendanceAuditLog::logEvent(
            eventType: 'user_account_rejection',
            actor: Auth::user(),
            affectedUser: $user,
            reason: 'Rejected pending staff account'
        );

        return back()->with('success', "Staff account for {$userName} was rejected.");
    }

    public function approveCredential(AttendanceCredential $credential)
    {
        $this->authorizeAdmin();

        $passkey = Passkey::where('user_id', $credential->user_id)
            ->where('credential_id', $credential->credential_id)
            ->first();

        if (! $passkey || ! $credential->user?->hrEmployee || $credential->user->hrEmployee->employment_status !== 'active') {
            return back()->with('error', 'This legacy or inactive device cannot be approved for ID-based sign-in. Ask the employee to register a new device with their HR employee ID.');
        }

        DB::transaction(function () use ($credential) {
            AttendanceCredential::where('user_id', $credential->user_id)
                ->where('id', '!=', $credential->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'deactivated_at' => now(),
                    'deactivated_by' => Auth::id(),
                ]);

            $credential->update([
                'approval_status' => 'approved',
                'is_active' => true,
            ]);

            if ($credential->user->status === 'pending') {
                $credential->user->update(['status' => 'approved']);
            }
        });

        AttendanceAuditLog::logEvent(
            eventType: 'device_approval',
            actor: Auth::user(),
            affectedUser: $credential->user,
            newValues: ['credential_id' => $credential->id, 'approval_status' => 'approved'],
            reason: 'Approved passkey device registration'
        );

        return back()->with('success', "Passkey device for {$credential->user->name} has been approved for attendance check-in.");
    }

    public function rejectCredential(AttendanceCredential $credential)
    {
        $this->authorizeAdmin();

        $credential->update([
            'approval_status' => 'rejected',
            'is_active' => false,
        ]);

        AttendanceAuditLog::logEvent(
            eventType: 'device_rejection',
            actor: Auth::user(),
            affectedUser: $credential->user,
            newValues: ['credential_id' => $credential->id, 'approval_status' => 'rejected'],
            reason: 'Rejected passkey device registration'
        );

        return back()->with('success', "Device registration for {$credential->user->name} was rejected.");
    }

    public function analytics(Request $request)
    {
        $this->authorizeAdmin();

        $search = trim((string) $request->input('search', ''));
        $roleFilter = $request->input('role');

        $usersQuery = User::orderBy('name');

        if ($search !== '') {
            $usersQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($roleFilter) {
            $usersQuery->where('role', $roleFilter);
        }

        $users = $usersQuery->get();

        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        $staffAnalytics = $users->map(function (User $u) use ($startOfWeek, $endOfWeek, $currentMonth, $currentYear) {
            $records = AttendanceRecord::where('user_id', $u->id)->get();

            $lateThisWeek = $records->filter(function ($r) use ($startOfWeek, $endOfWeek) {
                return $r->status === 'late' && $r->attendance_date && $r->attendance_date->between($startOfWeek, $endOfWeek);
            })->count();

            $lateThisMonth = $records->filter(function ($r) use ($currentMonth, $currentYear) {
                return $r->status === 'late' && $r->attendance_date && $r->attendance_date->month === $currentMonth && $r->attendance_date->year === $currentYear;
            })->count();

            $lateThisYear = $records->filter(function ($r) use ($currentYear) {
                return $r->status === 'late' && $r->attendance_date && $r->attendance_date->year === $currentYear;
            })->count();

            $lateAllTime = $records->where('status', 'late')->count();
            $presentAllTime = $records->whereNotNull('check_in_at')->count();

            $punctualityRate = $presentAllTime > 0
                ? round((($presentAllTime - $lateAllTime) / $presentAllTime) * 100, 1)
                : 100.0;

            return [
                'user' => $u,
                'late_this_week' => $lateThisWeek,
                'late_this_month' => $lateThisMonth,
                'late_this_year' => $lateThisYear,
                'late_all_time' => $lateAllTime,
                'present_all_time' => $presentAllTime,
                'punctuality_rate' => $punctualityRate,
            ];
        });

        // Summary company metrics
        $totalLateThisWeek = $staffAnalytics->sum('late_this_week');
        $totalLateThisMonth = $staffAnalytics->sum('late_this_month');
        $totalLateThisYear = $staffAnalytics->sum('late_this_year');
        $totalLateAllTime = $staffAnalytics->sum('late_all_time');
        $avgPunctualityRate = $staffAnalytics->count() > 0 ? round($staffAnalytics->avg('punctuality_rate'), 1) : 100;

        return view('admin.attendance.analytics', compact(
            'staffAnalytics',
            'search',
            'roleFilter',
            'totalLateThisWeek',
            'totalLateThisMonth',
            'totalLateThisYear',
            'totalLateAllTime',
            'avgPunctualityRate'
        ));
    }

    public function showUserHistory(User $user)
    {
        $this->authorizeAdmin();

        $credentials = AttendanceCredential::where('user_id', $user->id)
            ->orderBy('registered_at', 'desc')
            ->get();

        $records = AttendanceRecord::where('user_id', $user->id)
            ->orderBy('attendance_date', 'desc')
            ->paginate(20);

        $auditLogs = AttendanceAuditLog::where('affected_user_id', $user->id)
            ->orWhere('actor_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get();

        $allUserRecords = AttendanceRecord::where('user_id', $user->id)->get();

        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        $lateStats = [
            'week' => $allUserRecords->filter(fn ($r) => $r->status === 'late' && $r->attendance_date && $r->attendance_date->between($startOfWeek, $endOfWeek))->count(),
            'month' => $allUserRecords->filter(fn ($r) => $r->status === 'late' && $r->attendance_date && $r->attendance_date->month === $currentMonth && $r->attendance_date->year === $currentYear)->count(),
            'year' => $allUserRecords->filter(fn ($r) => $r->status === 'late' && $r->attendance_date && $r->attendance_date->year === $currentYear)->count(),
            'all_time' => $allUserRecords->where('status', 'late')->count(),
            'total_present' => $allUserRecords->whereNotNull('check_in_at')->count(),
        ];

        $lateStats['punctuality_rate'] = $lateStats['total_present'] > 0
            ? round((($lateStats['total_present'] - $lateStats['all_time']) / $lateStats['total_present']) * 100, 1)
            : 100.0;

        return view('admin.attendance.user_history', compact('user', 'credentials', 'records', 'auditLogs', 'lateStats'));
    }

    public function correctRecord(Request $request, AttendanceRecord $record)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:present,late,half_day,absent,manual_entry'],
            'check_in_at' => ['nullable', 'date'],
            'check_out_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $actor = Auth::user();
        $originalValues = [
            'status' => $record->status,
            'check_in_at' => $record->check_in_at?->toIso8601String(),
            'check_out_at' => $record->check_out_at?->toIso8601String(),
            'notes' => $record->notes,
        ];

        $newValues = [
            'status' => $validated['status'],
            'check_in_at' => ! empty($validated['check_in_at']) ? Carbon::parse($validated['check_in_at'])->toIso8601String() : null,
            'check_out_at' => ! empty($validated['check_out_at']) ? Carbon::parse($validated['check_out_at'])->toIso8601String() : null,
            'notes' => $validated['notes'] ?? $record->notes,
        ];

        $record->update([
            'status' => $validated['status'],
            'check_in_at' => ! empty($validated['check_in_at']) ? Carbon::parse($validated['check_in_at']) : $record->check_in_at,
            'check_out_at' => ! empty($validated['check_out_at']) ? Carbon::parse($validated['check_out_at']) : $record->check_out_at,
            'notes' => $validated['notes'] ?? $record->notes,
            'check_in_method' => $record->check_in_method ?: 'manual',
        ]);

        AttendanceAuditLog::logEvent(
            eventType: 'manual_correction',
            actor: $actor,
            affectedUser: $record->user,
            record: $record,
            originalValues: $originalValues,
            newValues: $newValues,
            reason: $validated['reason']
        );

        return back()->with('success', 'Attendance record updated cleanly and change audited.');
    }

    public function destroyRecord(AttendanceRecord $record)
    {
        $this->authorizeAdmin();

        $actor = Auth::user();
        $user = $record->user;
        $dateStr = $record->attendance_date->toDateString();

        AttendanceAuditLog::logEvent(
            eventType: 'manual_deletion',
            actor: $actor,
            affectedUser: $user,
            reason: "Attendance record for {$user->name} on {$dateStr} deleted by administrator."
        );

        $record->delete();

        return back()->with('success', "Attendance record for {$user->name} on {$dateStr} deleted successfully.");
    }

    public function fixTodayWatTimestamps(Request $request)
    {
        $this->authorizeAdmin();

        $tz = AttendanceSetting::get('timezone', config('app.timezone', 'Africa/Lagos'));
        $dateStr = $request->input('date', Carbon::today($tz)->toDateString());
        $hoursToAdd = (int) $request->input('hours', 1);

        $expectedTimeStr = AttendanceSetting::get('expected_arrival_time', '09:00');
        $lateThreshold = (int) AttendanceSetting::get('late_threshold_minutes', 15);

        $records = AttendanceRecord::whereDate('attendance_date', $dateStr)->get();

        if ($records->isEmpty()) {
            return back()->with('error', "No attendance records found for {$dateStr}.");
        }

        $actor = Auth::user();
        $count = 0;

        foreach ($records as $record) {
            if (! $record->check_in_at) {
                continue;
            }

            $newCheckIn = Carbon::parse($record->check_in_at)->addHours($hoursToAdd);
            $expectedArrival = Carbon::createFromFormat('Y-m-d H:i', $dateStr.' '.$expectedTimeStr, $tz);
            $lateDeadline = (clone $expectedArrival)->addMinutes($lateThreshold);

            $newStatus = $newCheckIn->greaterThan($lateDeadline) ? 'late' : 'present';

            $record->check_in_at = $newCheckIn;
            $record->status = $newStatus;

            if ($record->check_out_at) {
                $record->check_out_at = Carbon::parse($record->check_out_at)->addHours($hoursToAdd);
            }

            $record->save();
            $count++;

            AttendanceAuditLog::logEvent(
                eventType: 'manual_correction',
                actor: $actor,
                affectedUser: $record->user,
                record: $record,
                newValues: [
                    'check_in_at' => $newCheckIn->toIso8601String(),
                    'status' => $newStatus,
                ],
                reason: "Admin WAT Shift (+{$hoursToAdd} hr): Recalculated check-in to WAT and re-evaluated late status."
            );
        }

        return back()->with('success', "Successfully updated {$count} check-in record(s) for {$dateStr} to WAT and re-evaluated late status.");
    }

    public function storeManualRecord(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'attendance_date' => ['required', 'date'],
            'status' => ['required', 'string', 'in:present,late,half_day,absent,manual_entry'],
            'check_in_at' => ['nullable', 'date'],
            'check_out_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $actor = Auth::user();
        $targetUser = User::findOrFail($validated['user_id']);

        $record = AttendanceRecord::updateOrCreate(
            ['user_id' => $targetUser->id, 'attendance_date' => $validated['attendance_date']],
            [
                'status' => $validated['status'],
                'check_in_at' => ! empty($validated['check_in_at']) ? Carbon::parse($validated['check_in_at']) : null,
                'check_out_at' => ! empty($validated['check_out_at']) ? Carbon::parse($validated['check_out_at']) : null,
                'check_in_method' => 'manual',
                'notes' => $validated['notes'] ?? 'Manually created by admin',
            ]
        );

        AttendanceAuditLog::logEvent(
            eventType: 'manual_creation',
            actor: $actor,
            affectedUser: $targetUser,
            record: $record,
            newValues: $validated,
            reason: $validated['reason']
        );

        return back()->with('success', 'Manual attendance entry created successfully.');
    }

    public function deactivateCredential(Request $request, AttendanceCredential $credential)
    {
        $this->authorizeAdmin();

        $actor = Auth::user();
        $credential->update([
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivated_by' => $actor->id,
        ]);

        AttendanceAuditLog::logEvent(
            eventType: 'credential_deactivation',
            actor: $actor,
            affectedUser: $credential->user,
            newValues: ['credential_id' => $credential->id, 'is_active' => false],
            reason: $request->input('reason', 'Credential deactivated by administrator.')
        );

        return back()->with('success', 'Device credential deactivated.');
    }

    public function allowDeviceReplacement(Request $request, User $user)
    {
        $this->authorizeAdmin();

        $actor = Auth::user();

        $activeCredentials = AttendanceCredential::where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        foreach ($activeCredentials as $credential) {
            $credential->update([
                'is_active' => false,
                'deactivated_at' => now(),
                'deactivated_by' => $actor->id,
            ]);
        }

        AttendanceAuditLog::logEvent(
            eventType: 'device_replacement_allowed',
            actor: $actor,
            affectedUser: $user,
            reason: 'Authorized device replacement for staff member'
        );

        return back()->with('success', 'Previous device credential invalidated. Staff member can now register a new device.');
    }

    public function networks(Request $request)
    {
        $this->authorizeAdmin();

        $networks = AttendanceNetwork::orderBy('created_at', 'desc')->get();
        $currentIp = AttendanceController::resolveClientIp($request);

        // Resolve DDNS status for enabled DDNS networks
        foreach ($networks as $net) {
            if ($net->ddns_enabled) {
                $net->resolveDdns();
            }
        }

        // Auto IP Updater Status
        $autoIpCurrent = AttendanceSetting::get('office_ip_current');
        $autoIpStatus = AttendanceSetting::get('office_ip_update_status', 'not_run');
        $autoIpProvider = AttendanceSetting::get('office_ip_provider');
        $autoIpCheckedAt = AttendanceSetting::get('office_ip_last_checked_at');
        $autoIpChangedAt = AttendanceSetting::get('office_ip_last_changed_at');
        $autoIpError = AttendanceSetting::get('office_ip_last_error');

        return view('admin.attendance.networks', compact(
            'networks',
            'currentIp',
            'autoIpCurrent',
            'autoIpStatus',
            'autoIpProvider',
            'autoIpCheckedAt',
            'autoIpChangedAt',
            'autoIpError'
        ));
    }

    public function autoUpdateOfficeIp(Request $request)
    {
        $this->authorizeAdmin();

        try {
            Artisan::call('attendance:update-office-ip', ['--force' => true]);
            $output = trim(Artisan::output());

            return back()->with('success', "Office public IP auto-update completed: {$output}");
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to run public IP auto-updater: '.$e->getMessage());
        }
    }

    public function syncCurrentIp(Request $request)
    {
        $this->authorizeAdmin();

        $clientIp = AttendanceController::resolveClientIp($request);
        if (! $clientIp) {
            return back()->with('error', 'Could not resolve client IP.');
        }

        $network = AttendanceNetwork::first();
        if (! $network) {
            $network = AttendanceNetwork::create([
                'name' => 'HQ Main Office Wi-Fi',
                'ip_range' => $clientIp.'/32',
                'enabled' => true,
                'description' => 'Auto-synced to current admin public IP on '.Carbon::now()->format('M j, Y g:i A'),
                'created_by' => Auth::id(),
            ]);
        } else {
            $network->update([
                'ip_range' => $clientIp.'/32',
                'enabled' => true,
                'description' => 'Auto-synced to current admin public IP on '.Carbon::now()->format('M j, Y g:i A'),
            ]);
        }

        return back()->with('success', "Office Network updated to your current public IP ({$clientIp}/32).");
    }

    public function storeNetwork(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ip_range' => ['nullable', 'string', 'max:500'],
            'ddns_hostname' => ['nullable', 'string', 'max:255'],
            'ddns_enabled' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'enabled' => ['boolean'],
        ]);

        $ipRange = trim($validated['ip_range'] ?? '');
        $ddnsHostname = trim($validated['ddns_hostname'] ?? '');
        $ddnsEnabled = $request->boolean('ddns_enabled');

        if (empty($ipRange) && empty($ddnsHostname)) {
            return back()->with('error', 'Please provide either a static IP/CIDR range or a Dynamic DNS (DDNS) hostname.');
        }

        $network = AttendanceNetwork::create([
            'name' => $validated['name'],
            'ip_range' => $ipRange ?: '*',
            'ddns_hostname' => $ddnsHostname ?: null,
            'ddns_enabled' => $ddnsEnabled,
            'description' => $validated['description'] ?? null,
            'enabled' => $request->boolean('enabled', true),
            'created_by' => Auth::id(),
        ]);

        if ($network->ddns_enabled) {
            $network->resolveDdns(forceRefresh: true);
        }

        return back()->with('success', 'Office network range added successfully.');
    }

    public function updateNetwork(Request $request, AttendanceNetwork $network)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ip_range' => ['nullable', 'string', 'max:500'],
            'ddns_hostname' => ['nullable', 'string', 'max:255'],
            'ddns_enabled' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'enabled' => ['boolean'],
        ]);

        $ipRange = trim($validated['ip_range'] ?? '');
        $ddnsHostname = trim($validated['ddns_hostname'] ?? '');
        $ddnsEnabled = $request->boolean('ddns_enabled');

        $network->update([
            'name' => $validated['name'],
            'ip_range' => $ipRange,
            'ddns_hostname' => $ddnsHostname ?: null,
            'ddns_enabled' => $ddnsEnabled,
            'description' => $validated['description'] ?? null,
            'enabled' => $request->boolean('enabled'),
        ]);

        if ($network->ddns_enabled) {
            $network->resolveDdns(forceRefresh: true);
        }

        return back()->with('success', 'Office network updated successfully.');
    }

    public function refreshDdns(AttendanceNetwork $network)
    {
        $this->authorizeAdmin();

        $resolvedIp = $network->resolveDdns(forceRefresh: true);

        if ($resolvedIp) {
            return back()->with('success', "DDNS resolution updated for [{$network->name}]. Resolved IP: {$resolvedIp}");
        }

        if ($network->ddns_enabled && $network->effective_hostname) {
            return back()->with('error', "DDNS resolution failed for [{$network->name}] hostname [{$network->effective_hostname}]. Check DNS or status log.");
        }

        return back()->with('info', "DDNS is not enabled or hostname is empty for [{$network->name}].");
    }

    public function toggleNetwork(AttendanceNetwork $network)
    {
        $this->authorizeAdmin();

        $network->update(['enabled' => ! $network->enabled]);

        return back()->with('success', 'Network status updated.');
    }

    public function destroyNetwork(AttendanceNetwork $network)
    {
        $this->authorizeAdmin();

        $network->delete();

        return back()->with('success', 'Network range deleted.');
    }

    public function settings()
    {
        $this->authorizeAdmin();

        $settings = [
            'attendance_enabled' => AttendanceSetting::get('attendance_enabled', true),
            'check_in_start_time' => AttendanceSetting::get('check_in_start_time', '07:00'),
            'expected_arrival_time' => AttendanceSetting::get('expected_arrival_time', '09:00'),
            'check_in_closing_time' => AttendanceSetting::get('check_in_closing_time', '12:00'),
            'check_out_availability_time' => AttendanceSetting::get('check_out_availability_time', '16:00'),
            'late_threshold_minutes' => AttendanceSetting::get('late_threshold_minutes', 15),
            'auto_checkout_enabled' => AttendanceSetting::get('auto_checkout_enabled', true),
            'auto_checkout_time' => AttendanceSetting::get('auto_checkout_time', '17:00'),
            'timezone' => AttendanceSetting::get('timezone', config('app.timezone', 'UTC')),
            'working_days' => AttendanceSetting::get('working_days', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']),
        ];

        return view('admin.attendance.settings', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'attendance_enabled' => ['boolean'],
            'check_in_start_time' => ['required', 'string'],
            'expected_arrival_time' => ['required', 'string'],
            'check_in_closing_time' => ['required', 'string'],
            'check_out_availability_time' => ['required', 'string'],
            'late_threshold_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'auto_checkout_enabled' => ['boolean'],
            'auto_checkout_time' => ['required', 'string'],
            'timezone' => ['required', 'string'],
            'working_days' => ['required', 'array'],
        ]);

        AttendanceSetting::set('attendance_enabled', $request->boolean('attendance_enabled'));
        AttendanceSetting::set('check_in_start_time', $validated['check_in_start_time']);
        AttendanceSetting::set('expected_arrival_time', $validated['expected_arrival_time']);
        AttendanceSetting::set('check_in_closing_time', $validated['check_in_closing_time']);
        AttendanceSetting::set('check_out_availability_time', $validated['check_out_availability_time']);
        AttendanceSetting::set('late_threshold_minutes', (int) $validated['late_threshold_minutes']);
        AttendanceSetting::set('auto_checkout_enabled', $request->boolean('auto_checkout_enabled'));
        AttendanceSetting::set('auto_checkout_time', $validated['auto_checkout_time']);
        AttendanceSetting::set('timezone', $validated['timezone']);
        AttendanceSetting::set('working_days', $validated['working_days']);

        return back()->with('success', 'Attendance rules and auto check-out settings saved successfully.');
    }

    protected function autoCheckoutForgottenRecords(): void
    {
        if (! AttendanceSetting::get('auto_checkout_enabled', true)) {
            return;
        }

        $tz = AttendanceSetting::get('timezone', config('app.timezone', 'UTC'));
        $autoTime = AttendanceSetting::get('auto_checkout_time', '17:00');
        $nowTz = Carbon::now($tz);

        $unclosedRecords = AttendanceRecord::whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->get();

        foreach ($unclosedRecords as $rec) {
            $recDate = $rec->attendance_date;
            // Build the auto-checkout moment in the correct local timezone
            $autoCheckoutDateTime = Carbon::createFromFormat(
                'Y-m-d H:i',
                $recDate->toDateString().' '.$autoTime,
                $tz
            );

            if ($nowTz->greaterThanOrEqualTo($autoCheckoutDateTime)) {
                $rec->update([
                    'check_out_at' => $autoCheckoutDateTime,
                    'notes' => trim(($rec->notes ? $rec->notes.' | ' : '').'System Auto Check-Out (Forgotten Check-Out)'),
                ]);

                AttendanceAuditLog::logEvent(
                    eventType: 'auto_checkout',
                    actor: null,
                    affectedUser: $rec->user,
                    record: $rec,
                    reason: 'Automatic Auto Check-Out triggered for forgotten check-out'
                );
            }
        }
    }
}
