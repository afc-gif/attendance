<?php

use App\Http\Controllers\Attendance\AdminAttendanceController as AdminAttendance;
use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Attendance\DeploymentMonitorController;
use App\Http\Controllers\Attendance\UserManagementController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// Authentication & Self-Registration Routes
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Authenticated Staff Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/', [AttendanceController::class, 'dashboard'])->name('attendance.dashboard');
    Route::get('/attendance', [AttendanceController::class, 'dashboard']);
    Route::get('/attendance/history', [AttendanceController::class, 'history'])->name('attendance.history');

    // WebAuthn Passkey API Endpoints
    Route::get('/attendance/register-options', [AttendanceController::class, 'getRegisterOptions'])->name('attendance.register-options');
    Route::post('/attendance/register-device', [AttendanceController::class, 'registerDevice'])->name('attendance.register-device');
    Route::get('/attendance/assertion-options', [AttendanceController::class, 'getAssertionOptions'])->name('attendance.assertion-options');
    Route::post('/attendance/client-error', [AttendanceController::class, 'logClientError'])->name('attendance.client-error');
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
    Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');

    // Staff Payroll Statement
    Route::get('/staff/payroll', [\App\Http\Controllers\Attendance\StaffPayrollController::class, 'index'])->name('staff.payroll.index');

    // Administrator-only Deployment Monitor (authorization is enforced in the controller).
    Route::get('/admin/deployments', [DeploymentMonitorController::class, 'index'])->name('admin.deployments.index');
    Route::get('/admin/deployments/data', [DeploymentMonitorController::class, 'data'])->name('admin.deployments.data');

    // Admin Payroll Management Routes
    Route::prefix('admin/payroll')->name('admin.payroll.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Attendance\AdminPayrollController::class, 'index'])->name('index');
        Route::post('/staff/{user}', [\App\Http\Controllers\Attendance\AdminPayrollController::class, 'updateStaffProfile'])->name('staff.update');
        Route::post('/deductions/manual', [\App\Http\Controllers\Attendance\AdminPayrollController::class, 'storeManualDeduction'])->name('deductions.manual');
        Route::post('/deductions/{deduction}/waive', [\App\Http\Controllers\Attendance\AdminPayrollController::class, 'waiveDeduction'])->name('deductions.waive');
        Route::post('/reset', [\App\Http\Controllers\Attendance\AdminPayrollController::class, 'executeReset'])->name('reset');
    });

    // Admin Shift Template Routes
    Route::prefix('admin/shifts')->name('admin.shifts.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Attendance\AdminPayrollController::class, 'shiftsIndex'])->name('index');
        Route::post('/', [\App\Http\Controllers\Attendance\AdminPayrollController::class, 'storeShift'])->name('store');
        Route::put('/{shift}', [\App\Http\Controllers\Attendance\AdminPayrollController::class, 'updateShift'])->name('update');
    });

    // Admin & Management Routes
    Route::prefix('admin/attendance')->name('admin.attendance.')->group(function () {
        Route::get('/', [AdminAttendance::class, 'index'])->name('index');
        Route::get('/analytics', [AdminAttendance::class, 'analytics'])->name('analytics');
        Route::get('/user/{user}', [AdminAttendance::class, 'showUserHistory'])->name('user-history');

        // Account & Device Approvals
        Route::post('/users/{user}/approve', [AdminAttendance::class, 'approveUser'])->name('users.approve');
        Route::post('/users/{user}/reject', [AdminAttendance::class, 'rejectUser'])->name('users.reject');
        Route::post('/credentials/{credential}/approve', [AdminAttendance::class, 'approveCredential'])->name('credentials.approve');
        Route::post('/credentials/{credential}/reject', [AdminAttendance::class, 'rejectCredential'])->name('credentials.reject');

        // Record Corrections
        Route::post('/correct/{record}', [AdminAttendance::class, 'correctRecord'])->name('correct');
        Route::delete('/records/{record}', [AdminAttendance::class, 'destroyRecord'])->name('records.destroy');
        Route::post('/fix-today-timestamps', [AdminAttendance::class, 'fixTodayWatTimestamps'])->name('fix-today-timestamps');
        Route::post('/manual-record', [AdminAttendance::class, 'storeManualRecord'])->name('manual-record');
        Route::post('/deactivate-credential/{credential}', [AdminAttendance::class, 'deactivateCredential'])->name('deactivate-credential');
        Route::post('/allow-replacement/{user}', [AdminAttendance::class, 'allowDeviceReplacement'])->name('allow-replacement');

        // Networks
        Route::get('/networks', [AdminAttendance::class, 'networks'])->name('networks');
        Route::post('/networks', [AdminAttendance::class, 'storeNetwork'])->name('networks.store');
        Route::post('/networks/sync-ip', [AdminAttendance::class, 'syncCurrentIp'])->name('networks.sync-ip');
        Route::post('/networks/auto-update-ip', [AdminAttendance::class, 'autoUpdateOfficeIp'])->name('networks.auto-update-ip');
        Route::put('/networks/{network}', [AdminAttendance::class, 'updateNetwork'])->name('networks.update');
        Route::post('/networks/{network}/toggle', [AdminAttendance::class, 'toggleNetwork'])->name('networks.toggle');
        Route::post('/networks/{network}/refresh-ddns', [AdminAttendance::class, 'refreshDdns'])->name('networks.refresh-ddns');
        Route::delete('/networks/{network}', [AdminAttendance::class, 'destroyNetwork'])->name('networks.destroy');

        // Settings
        Route::get('/settings', [AdminAttendance::class, 'settings'])->name('settings');
        Route::post('/settings', [AdminAttendance::class, 'updateSettings'])->name('settings.update');
    });

    // User Management Routes
    Route::prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::get('/create', [UserManagementController::class, 'create'])->name('create');
        Route::post('/', [UserManagementController::class, 'store'])->name('store');
        Route::get('/{user}', [UserManagementController::class, 'show'])->name('show');
        Route::get('/{user}/edit', [UserManagementController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
        Route::patch('/{user}/status', [UserManagementController::class, 'updateStatus'])->name('status');
        Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('destroy');
    });
});
