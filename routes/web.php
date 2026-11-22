<?php

use App\Http\Controllers\Attendance\AdminAttendanceController as AdminAttendance;
use App\Http\Controllers\Attendance\AdminPayrollController;
use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Attendance\DeploymentMonitorController;
use App\Http\Controllers\Attendance\StaffPayrollController;
use App\Http\Controllers\Attendance\UserManagementController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmployeeInvitationController;
use App\Http\Controllers\Hr\DashboardController;
use App\Http\Controllers\Hr\DocumentController;
use App\Http\Controllers\Hr\EmployeeController;
use App\Http\Controllers\Hr\EmploymentController;
use App\Http\Controllers\Hr\IdCardController;
use App\Http\Controllers\Hr\OrganizationController;
use App\Http\Controllers\Hr\ReportController;
use App\Http\Controllers\Hr\SettingController;
use Illuminate\Support\Facades\Route;

// Authentication & Self-Registration Routes
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::get('/invitation/{token}', [EmployeeInvitationController::class, 'show'])
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:10,1')
    ->name('employee-invitations.show');
Route::post('/invitation/{token}', [EmployeeInvitationController::class, 'accept'])
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:10,1')
    ->name('employee-invitations.accept');
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
    Route::get('/staff/payroll', [StaffPayrollController::class, 'index'])->name('staff.payroll.index');

    // Administrator-only Deployment Monitor (authorization is enforced in the controller).
    Route::get('/admin/deployments', [DeploymentMonitorController::class, 'index'])->name('admin.deployments.index');
    Route::get('/admin/deployments/data', [DeploymentMonitorController::class, 'data'])->name('admin.deployments.data');

    // Admin Payroll Management Routes
    Route::prefix('admin/payroll')->name('admin.payroll.')->group(function () {
        Route::get('/', [AdminPayrollController::class, 'index'])->name('index');
        Route::post('/staff/{user}', [AdminPayrollController::class, 'updateStaffProfile'])->name('staff.update');
        Route::post('/deductions/manual', [AdminPayrollController::class, 'storeManualDeduction'])->name('deductions.manual');
        Route::post('/deductions/{deduction}/waive', [AdminPayrollController::class, 'waiveDeduction'])->name('deductions.waive');
        Route::post('/reset', [AdminPayrollController::class, 'executeReset'])->name('reset');
    });

    // Admin Shift Template Routes
    Route::prefix('admin/shifts')->name('admin.shifts.')->group(function () {
        Route::get('/', [AdminPayrollController::class, 'shiftsIndex'])->name('index');
        Route::post('/', [AdminPayrollController::class, 'storeShift'])->name('store');
        Route::put('/{shift}', [AdminPayrollController::class, 'updateShift'])->name('update');
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

    // HR Management - server-side restricted to Super Admin.
    Route::middleware('super_admin')->prefix('admin/hr')->name('admin.hr.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::post('/employees/{employee}/invitation', [EmployeeController::class, 'sendInvitation'])->name('employees.invitation.send');
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
        Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
        Route::post('/employees/{employee}/passport-photo', [EmployeeController::class, 'uploadPassportPhoto'])->name('employees.passport.upload');
        Route::delete('/employees/{employee}/passport-photo', [EmployeeController::class, 'deletePassportPhoto'])->name('employees.passport.destroy');
        Route::get('/employees/{employee}/passport-photo', [EmployeeController::class, 'passportPhoto'])->name('employees.passport.show');

        Route::post('/employees/{employee}/id-card/generate', [IdCardController::class, 'generate'])->name('employees.id-card.generate');
        Route::get('/employees/{employee}/id-card', [IdCardController::class, 'preview'])->name('employees.id-card.preview');
        Route::get('/employees/{employee}/id-card/pdf', [IdCardController::class, 'downloadPdf'])->name('employees.id-card.download');

        Route::get('/organization', [OrganizationController::class, 'index'])->name('organization.index');
        Route::post('/departments', [OrganizationController::class, 'storeDepartment'])->name('departments.store');
        Route::put('/departments/{department}', [OrganizationController::class, 'updateDepartment'])->name('departments.update');
        Route::patch('/departments/{department}/toggle', [OrganizationController::class, 'toggleDepartment'])->name('departments.toggle');
        Route::post('/positions', [OrganizationController::class, 'storePosition'])->name('positions.store');
        Route::put('/positions/{position}', [OrganizationController::class, 'updatePosition'])->name('positions.update');
        Route::patch('/positions/{position}/toggle', [OrganizationController::class, 'togglePosition'])->name('positions.toggle');

        Route::get('/employment', [EmploymentController::class, 'index'])->name('employment.index');

        Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('/employees/{employee}/documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    });
});
