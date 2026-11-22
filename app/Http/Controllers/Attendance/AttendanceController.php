<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceAuditLog;
use App\Models\AttendanceCredential;
use App\Models\AttendanceNetwork;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    protected PayrollService $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    /**
     * Return the configured timezone (from DB settings), defaulting to UTC.
     */
    protected static function appTz(): string
    {
        return AttendanceSetting::get('timezone', config('app.timezone', 'Africa/Lagos'));
    }

    /**
     * Return Carbon::now() in the configured attendance timezone.
     */
    protected static function nowTz(): Carbon
    {
        return Carbon::now(self::appTz());
    }

    /**
     * Return Carbon::today() in the configured attendance timezone.
     */
    protected static function todayTz(): Carbon
    {
        return Carbon::today(self::appTz());
    }

    /**
     * Proxy-aware real client IP resolution using Laravel trusted-proxy middleware (Railway / Cloudflare Tunnel).
     */
    public static function resolveClientIp(Request $request): string
    {
        // When request is received through a trusted proxy (e.g. Cloudflare Tunnel / cloudflared on 127.0.0.1),
        // prioritize CF-Connecting-IP header so Cloudflare edge proxies are never mistaken for the visitor.
        if ($request->isFromTrustedProxy() && $cfIp = $request->header('CF-Connecting-IP')) {
            $candidate = trim(explode(',', $cfIp)[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        $ip = $request->ip();
        if (! empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        return '127.0.0.1';
    }

    public function dashboard(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $tz = self::appTz();
        $today = self::todayTz();

        $record = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('attendance_date', $today->toDateString())
            ->first();

        $activeCredential = $user->activeAttendanceCredential;

        // Network verification
        $clientIp = self::resolveClientIp($request);
        $enabledNetworks = AttendanceNetwork::where('enabled', true)->get();

        $isNetworkVerified = false;
        $matchedNetwork = null;

        if ($enabledNetworks->isEmpty()) {
            $isNetworkVerified = true;
        } else {
            foreach ($enabledNetworks as $network) {
                if ($network->matchesIp($clientIp)) {
                    $isNetworkVerified = true;
                    $matchedNetwork = $network;
                    break;
                }
            }
        }

        if ($record) {
            $this->syncRecordStatusWithTimezone($record);
            $record->refresh();
        }

        $attendanceEnabled = AttendanceSetting::get('attendance_enabled', true);
        $profile = $user->staffProfile;
        $expectedArrival = $profile ? $profile->getExpectedResumptionTime($today, AttendanceSetting::get('expected_arrival_time', '08:00')) : AttendanceSetting::get('expected_arrival_time', '08:00');
        $checkInStart = AttendanceSetting::get('check_in_start_time', '07:00');
        $checkInClosing = AttendanceSetting::get('check_in_closing_time', '12:00');

        $currentPayPeriod = PayrollService::currentPayPeriod();
        $payrollSummary = $this->payrollService->calculateMonthlyBalance($user, $currentPayPeriod);

        return response()
            ->view('attendance.dashboard', compact(
                'user',
                'today',
                'tz',
                'record',
                'activeCredential',
                'isNetworkVerified',
                'matchedNetwork',
                'clientIp',
                'attendanceEnabled',
                'expectedArrival',
                'checkInStart',
                'checkInClosing',
                'payrollSummary'
            ))
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    protected function syncRecordStatusWithTimezone(?AttendanceRecord $record): void
    {
        if (! $record || ! $record->check_in_at) {
            return;
        }

        $tz = self::appTz();
        $dateStr = $record->attendance_date->toDateString();
        $user = $record->user;
        $profile = $user?->staffProfile;

        $expectedTimeStr = $profile ? $profile->getExpectedResumptionTime($record->attendance_date, AttendanceSetting::get('expected_arrival_time', '08:00')) : AttendanceSetting::get('expected_arrival_time', '08:00');
        $lateThreshold = $profile ? $profile->grace_period_minutes : (int) AttendanceSetting::get('late_threshold_minutes', 15);

        // Convert check_in_at to local WAT timezone
        $checkInLocal = Carbon::parse($record->check_in_at)->setTimezone($tz);

        // Calculate expected arrival deadline in WAT
        $expectedArrival = Carbon::createFromFormat('Y-m-d H:i', $dateStr.' '.$expectedTimeStr, $tz);
        $lateDeadline = (clone $expectedArrival)->addMinutes($lateThreshold);

        $isOffDay = $profile ? $profile->isOffDay($record->attendance_date) : false;

        $expectedStatus = ($isOffDay) ? 'present' : ($checkInLocal->greaterThan($lateDeadline) ? 'late' : 'present');

        if ($record->status !== $expectedStatus) {
            $record->update(['status' => $expectedStatus]);
        }
    }

    protected function verifiedPasskeyCredential(Request $request, User $user): ?AttendanceCredential
    {
        return AttendanceCredential::whereKey($request->session()->get('staff_passkey_credential_id'))
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where('approval_status', 'approved')
            ->first();
    }

    public function checkIn(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $tz = self::appTz();
        $today = self::todayTz();

        // 1. Attendance module enabled?
        if (! AttendanceSetting::get('attendance_enabled', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance module is currently disabled by administrator.',
            ], 422);
        }

        // 2. Already checked in today?
        $existingRecord = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('attendance_date', $today->toDateString())
            ->first();

        if ($existingRecord && $existingRecord->check_in_at) {
            return response()->json([
                'success' => false,
                'message' => 'You have already checked in for today ('
                    .$existingRecord->check_in_at->setTimezone($tz)->format('g:i A').').',
            ], 422);
        }

        // 3. Network verification
        $clientIp = self::resolveClientIp($request);
        $enabledNetworks = AttendanceNetwork::where('enabled', true)->get();
        $isNetworkVerified = false;

        if ($enabledNetworks->isEmpty()) {
            $isNetworkVerified = true;
        } else {
            foreach ($enabledNetworks as $network) {
                if ($network->matchesIp($clientIp)) {
                    $isNetworkVerified = true;
                    break;
                }
            }
        }

        if (! $isNetworkVerified) {
            AttendanceAuditLog::logEvent(
                eventType: 'failed_network_verification',
                actor: $user,
                affectedUser: $user,
                reason: 'Check-in attempt from unapproved network IP: '.$clientIp
            );

            return response()->json([
                'success' => false,
                'message' => 'Check-in failed. Request must originate from an approved office network (Your IP: '.$clientIp.').',
            ], 403);
        }

        // The authenticated session was created only after a verified passkey assertion.
        $credential = $this->verifiedPasskeyCredential($request, $user);
        if (! $credential) {
            return response()->json([
                'success' => false,
                'message' => 'Sign in again with your approved device before recording attendance.',
            ], 422);
        }

        // 5. Evaluate late status using staff profile shift/resumption & off-days
        $now = self::nowTz();
        $profile = $user->staffProfile;
        $expectedTimeStr = $profile ? $profile->getExpectedResumptionTime($today, AttendanceSetting::get('expected_arrival_time', '08:00')) : AttendanceSetting::get('expected_arrival_time', '08:00');
        $lateThreshold = $profile ? $profile->grace_period_minutes : (int) AttendanceSetting::get('late_threshold_minutes', 15);

        // Build expected arrival for today in the correct timezone
        $expectedArrival = Carbon::createFromFormat('Y-m-d H:i', $today->toDateString().' '.$expectedTimeStr, $tz);
        $lateDeadline = (clone $expectedArrival)->addMinutes($lateThreshold);

        $isOffDay = $profile ? $profile->isOffDay($today) : false;
        $status = ($isOffDay) ? 'present' : ($now->greaterThan($lateDeadline) ? 'late' : 'present');

        // 6. Record attendance
        $record = AttendanceRecord::updateOrCreate(
            ['user_id' => $user->id, 'attendance_date' => $today->toDateString()],
            [
                'check_in_at' => $now,
                'status' => $status,
                'check_in_method' => 'passkey',
                'check_in_network_verified' => true,
                'check_in_ip' => $clientIp,
                'check_in_credential_id' => $credential->id,
            ]
        );

        // 7. Apply lateness penalty if late and not an off-day
        if ($status === 'late') {
            $this->payrollService->evaluateAndApplyLatenessPenalty($record, $user);
        }

        AttendanceAuditLog::logEvent(
            eventType: 'check_in',
            actor: $user,
            affectedUser: $user,
            record: $record,
            newValues: ['check_in_at' => $now->toIso8601String(), 'status' => $status],
            reason: 'Check-in through a passkey-authenticated session'
        );

        return response()->json([
            'success' => true,
            'message' => 'Check-in successful',
            'check_in_time' => $now->format('g:i A'),
            'status' => $status,
            'record' => $record,
        ]);
    }

    public function checkOut(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $tz = self::appTz();
        $today = self::todayTz();

        $record = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('attendance_date', $today->toDateString())
            ->first();

        if (! $record || ! $record->check_in_at) {
            return response()->json([
                'success' => false,
                'message' => 'You must check in first before checking out.',
            ], 422);
        }

        if ($record->check_out_at) {
            return response()->json([
                'success' => false,
                'message' => 'You have already checked out for today at '
                    .$record->check_out_at->setTimezone($tz)->format('g:i A').'.',
            ], 422);
        }

        // Network verification
        $clientIp = self::resolveClientIp($request);
        $enabledNetworks = AttendanceNetwork::where('enabled', true)->get();
        $isNetworkVerified = false;

        if ($enabledNetworks->isEmpty()) {
            $isNetworkVerified = true;
        } else {
            foreach ($enabledNetworks as $network) {
                if ($network->matchesIp($clientIp)) {
                    $isNetworkVerified = true;
                    break;
                }
            }
        }

        if (! $isNetworkVerified) {
            AttendanceAuditLog::logEvent(
                eventType: 'failed_network_verification',
                actor: $user,
                affectedUser: $user,
                reason: 'Check-out attempt from unapproved network IP: '.$clientIp
            );

            return response()->json([
                'success' => false,
                'message' => 'Check-out failed. Request must originate from an approved office network (Your IP: '.$clientIp.').',
            ], 403);
        }

        $credential = $this->verifiedPasskeyCredential($request, $user);
        if (! $credential) {
            return response()->json([
                'success' => false,
                'message' => 'Sign in again with your approved device before recording attendance.',
            ], 422);
        }

        $now = self::nowTz();
        $record->update([
            'check_out_at' => $now,
            'check_out_network_verified' => true,
            'check_out_ip' => $clientIp,
            'check_out_credential_id' => $credential->id,
        ]);

        AttendanceAuditLog::logEvent(
            eventType: 'check_out',
            actor: $user,
            affectedUser: $user,
            record: $record,
            newValues: ['check_out_at' => $now->toIso8601String()],
            reason: 'Check-out through a passkey-authenticated session'
        );

        return response()->json([
            'success' => true,
            'message' => 'Check-out successful',
            'check_out_time' => $now->format('g:i A'),
            'record' => $record,
        ]);
    }

    public function history()
    {
        $user = Auth::user();
        $records = AttendanceRecord::where('user_id', $user->id)
            ->orderBy('attendance_date', 'desc')
            ->paginate(15);

        foreach ($records as $r) {
            $this->syncRecordStatusWithTimezone($r);
        }

        return response()
            ->view('attendance.history', compact('user', 'records'))
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
