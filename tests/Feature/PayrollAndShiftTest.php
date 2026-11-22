<?php

namespace Tests\Feature;

use App\Models\AttendanceCredential;
use App\Models\AttendanceNetwork;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\SalaryDeduction;
use App\Models\Shift;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollAndShiftTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'status' => 'approved',
        ]);
    }

    protected function createStaff(string $role = 'artsci_staff', float $salary = 150000.00): User
    {
        $user = User::factory()->create([
            'role' => $role,
            'status' => 'approved',
        ]);

        $company = $role === 'artsci_staff' ? 'ARTSCI' : 'AFC';
        Employee::create([
            'user_id' => $user->id,
            'employee_id' => ($company === 'ARTSCI' ? 'ART' : 'AFC').'-TEST-'.$user->id,
            'company' => $company,
            'first_name' => 'Test',
            'last_name' => 'Staff',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'confirmation_status' => 'confirmed',
        ]);
        $user->getOrCreateStaffProfile()->update([
            'base_salary' => $salary,
            'off_days' => [0], // Sunday off
            'grace_period_minutes' => 15,
        ]);

        return $user;
    }

    protected function registerCredentialForUser(User $user): AttendanceCredential
    {
        $credentialId = 'cred_'.$user->id.'_key';
        $credential = AttendanceCredential::create([
            'user_id' => $user->id,
            'credential_id' => $credentialId,
            'public_key' => 'pubkey_'.$user->id,
            'device_name' => 'Staff Mobile Device',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);
        $user->passkeys()->create([
            'name' => 'Staff Mobile Device',
            'credential_id' => $credentialId,
            'credential' => [],
        ]);
        session(['staff_passkey_credential_id' => $credential->id]);

        return $credential;
    }

    protected function createApprovedNetwork(): AttendanceNetwork
    {
        return AttendanceNetwork::create([
            'name' => 'Office Network',
            'ip_range' => '127.0.0.1/32',
            'enabled' => true,
        ]);
    }

    /** 1. Off-day check-in does not trigger lateness penalty */
    public function test_off_day_check_in_does_not_incur_lateness_penalty(): void
    {
        $staff = $this->createStaff();
        // Set Sunday (0) as off day
        $staff->getOrCreateStaffProfile()->update(['off_days' => [0]]);

        // Mock Sunday date
        Carbon::setTestNow(Carbon::parse('2026-09-20 11:00:00', 'Africa/Lagos')); // Sunday 11am

        $record = AttendanceRecord::create([
            'user_id' => $staff->id,
            'attendance_date' => '2026-09-20',
            'check_in_at' => now(),
            'status' => 'present',
        ]);

        $payrollService = new PayrollService;
        $deduction = $payrollService->evaluateAndApplyLatenessPenalty($record, $staff);

        $this->assertNull($deduction);
        $this->assertDatabaseMissing('salary_deductions', ['attendance_record_id' => $record->id]);
    }

    /** 2. Late check-in on working day automatically logs ₦500 deduction */
    public function test_late_check_in_logs_lateness_deduction(): void
    {
        $staff = $this->createStaff('artsci_staff', 150000.00);
        $this->createApprovedNetwork();
        $this->registerCredentialForUser($staff);

        // Monday 10:30 AM (expected resumption 08:00 AM + 15 mins)
        Carbon::setTestNow(Carbon::parse('2026-09-21 10:30:00', 'Africa/Lagos'));

        $response = $this->actingAs($staff)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('attendance.check-in'), []);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'late']);

        $this->assertDatabaseHas('salary_deductions', [
            'user_id' => $staff->id,
            'amount' => 500.00,
            'status' => 'active',
            'pay_period' => '2026-09',
        ]);
    }

    /** 3. Admin can waive penalty and restore net balance */
    public function test_admin_can_waive_lateness_penalty(): void
    {
        $admin = $this->createAdmin();
        $staff = $this->createStaff('afc_staff', 150000.00);

        $deduction = SalaryDeduction::create([
            'user_id' => $staff->id,
            'pay_period' => '2026-09',
            'amount' => 500.00,
            'status' => 'active',
            'reason' => 'Late check-in',
        ]);

        $payrollService = new PayrollService;
        $balanceBefore = $payrollService->calculateMonthlyBalance($staff, '2026-09');
        $this->assertEquals(149500.00, $balanceBefore['net_salary']);

        $response = $this->actingAs($admin)
            ->post(route('admin.payroll.deductions.waive', $deduction), [
                'waiver_reason' => 'Approved official duty',
            ]);

        $response->assertRedirect();

        $deduction->refresh();
        $this->assertEquals('waived', $deduction->status);
        $this->assertEquals('Approved official duty', $deduction->waiver_reason);

        $balanceAfter = $payrollService->calculateMonthlyBalance($staff, '2026-09');
        $this->assertEquals(150000.00, $balanceAfter['net_salary']);
    }

    /** 4. Monthly payroll reset archives period and resets deductions */
    public function test_monthly_payroll_reset_archives_period(): void
    {
        $admin = $this->createAdmin();
        $staff = $this->createStaff('artsci_staff', 200000.00);

        SalaryDeduction::create([
            'user_id' => $staff->id,
            'pay_period' => '2026-09',
            'amount' => 500.00,
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.payroll.reset'), [
                'pay_period' => '2026-09',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('payroll_archives', [
            'user_id' => $staff->id,
            'pay_period' => '2026-09',
            'base_salary' => 200000.00,
            'total_deductions' => 500.00,
            'net_salary' => 199500.00,
        ]);
    }

    /** 5. Admin can create shift template */
    public function test_admin_can_create_shift_template(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.shifts.store'), [
                'name' => 'Shift 1 (Morning)',
                'resumption_time' => '07:00',
                'closing_time' => '15:00',
                'department' => 'acf',
                'description' => 'Morning shift schedule',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('shifts', [
            'name' => 'Shift 1 (Morning)',
            'department' => 'acf',
        ]);
    }

    /** 6. Saturday resumption for ARTSCI staff defaults to 09:00 AM (not late before 09:15 AM) */
    public function test_artsci_saturday_resumption_defaults_to_9am(): void
    {
        $staff = $this->createStaff('artsci_staff', 150000.00);

        // Saturday 2026-09-26 at 09:10 AM (resumption 09:00 AM + 15 min grace = 09:15 AM deadline)
        $saturdayDate = Carbon::parse('2026-09-26 09:10:00', 'Africa/Lagos');

        $expectedResumption = $staff->staffProfile->getExpectedResumptionTime($saturdayDate);
        $this->assertEquals('09:00', $expectedResumption);

        $record = AttendanceRecord::create([
            'user_id' => $staff->id,
            'attendance_date' => '2026-09-26',
            'check_in_at' => $saturdayDate,
            'status' => 'present',
        ]);

        $payrollService = new PayrollService;
        $deduction = $payrollService->evaluateAndApplyLatenessPenalty($record, $staff);

        $this->assertNull($deduction);
        $this->assertDatabaseMissing('salary_deductions', ['attendance_record_id' => $record->id]);
    }

    /** 7. Admin can apply a custom manual penalty / deduction to staff */
    public function test_admin_can_apply_manual_salary_deduction(): void
    {
        $admin = $this->createAdmin();
        $staff = $this->createStaff('artsci_staff', 180000.00);

        $response = $this->actingAs($admin)
            ->post(route('admin.payroll.deductions.manual'), [
                'user_id' => $staff->id,
                'amount' => 2500.00,
                'reason' => 'Property damage misconduct penalty',
                'pay_period' => '2026-09',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('salary_deductions', [
            'user_id' => $staff->id,
            'amount' => 2500.00,
            'deduction_type' => 'manual_penalty',
            'reason' => 'Property damage misconduct penalty',
            'status' => 'active',
            'pay_period' => '2026-09',
        ]);

        $payrollService = new PayrollService;
        $balance = $payrollService->calculateMonthlyBalance($staff, '2026-09');
        $this->assertEquals(177500.00, $balance['net_salary']);
        $this->assertEquals(2500.00, $balance['total_deductions']);
    }
}
