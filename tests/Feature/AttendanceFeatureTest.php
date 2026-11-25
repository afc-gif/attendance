<?php

namespace Tests\Feature;

use App\Models\AttendanceCredential;
use App\Models\AttendanceNetwork;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttendanceFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function createUser(array $overrides = []): User
    {
        $user = User::create(array_merge([
            'name' => 'John Staff',
            'email' => 'staff_'.Str::random(6).'@example.com',
            'password' => bcrypt('password123'),
            'role' => 'afc_staff',
            'status' => 'approved',
        ], $overrides));

        if (in_array($user->role, ['afc_staff', 'artsci_staff'], true)) {
            $company = $user->role === 'artsci_staff' ? 'ARTSCI' : 'AFC';
            Employee::create([
                'user_id' => $user->id,
                'employee_id' => ($company === 'ARTSCI' ? 'ART' : 'AFC').'-TEST-'.Str::upper(Str::random(8)),
                'company' => $company,
                'first_name' => 'John',
                'last_name' => 'Staff',
                'employment_type' => 'full_time',
                'employment_status' => 'active',
                'confirmation_status' => 'confirmed',
            ]);

            if ($user->status === 'approved') {
                $credential = $this->registerCredentialForUser(
                    $user,
                    'test-credential-'.Str::random(10),
                    'approved',
                );
                session(['staff_passkey_credential_id' => $credential->id]);
            }
        }

        return $user;
    }

    protected function createAdmin(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Admin Manager',
            'email' => 'admin_'.Str::random(6).'@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'status' => 'approved',
        ], $overrides));
    }

    protected function createApprovedNetwork(string $ipRange = '127.0.0.1/32'): AttendanceNetwork
    {
        return AttendanceNetwork::create([
            'name' => 'Office HQ Wi-Fi',
            'ip_range' => $ipRange,
            'enabled' => true,
        ]);
    }

    protected function registerCredentialForUser(User $user, string $credentialId, string $approvalStatus = 'approved'): AttendanceCredential
    {
        return AttendanceCredential::create([
            'user_id' => $user->id,
            'credential_id' => $credentialId,
            'public_key' => 'pubkey_sample_data',
            'device_name' => "John's Phone",
            'is_active' => true,
            'approval_status' => $approvalStatus,
            'registered_at' => now(),
        ]);
    }

    public function test_admin_can_delete_user_with_linked_staff_data(): void
    {
        $admin = $this->createAdmin();
        $staff = $this->createUser();
        $employee = $staff->hrEmployee;
        $credential = $staff->attendanceCredentials()->firstOrFail();
        $record = AttendanceRecord::create([
            'user_id' => $staff->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'check_in_at' => now(),
            'status' => 'present',
            'check_in_credential_id' => $credential->id,
        ]);
        $staff->getOrCreateStaffProfile();
        $staff->passkeys()->create([
            'name' => 'Staff phone',
            'credential_id' => 'passkey-delete-test',
            'credential' => [],
        ]);
        DB::statement('PRAGMA defer_foreign_keys = ON');
        $auditLogId = DB::table('attendance_audit_logs')->insertGetId([
            'event_type' => 'user_delete_test',
            'actor_id' => $admin->id,
            'affected_user_id' => $staff->id,
            'attendance_record_id' => 999999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $staff))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
        $this->assertDatabaseMissing('attendance_credentials', ['id' => $credential->id]);
        $this->assertDatabaseMissing('attendance_records', ['id' => $record->id]);
        $this->assertDatabaseMissing('staff_profiles', ['user_id' => $staff->id]);
        $this->assertDatabaseMissing('passkeys', ['user_id' => $staff->id]);
        $this->assertDatabaseHas('attendance_audit_logs', [
            'id' => $auditLogId,
            'actor_id' => $admin->id,
            'affected_user_id' => null,
            'attendance_record_id' => null,
        ]);
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'user_id' => null,
        ]);
    }

    /** 1. Staff registration is managed by HR */
    public function test_staff_cannot_self_register_without_an_invitation(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'New Staff User',
            'email' => 'newstaff@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'artsci_staff',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHas('status', 'Staff accounts are created by HR. Contact HR for your employee ID.');
        $this->assertDatabaseMissing('users', ['email' => 'newstaff@example.com']);
    }

    /** 2. Admin can approve a pending staff account and change role */
    public function test_admin_can_approve_pending_staff_account_and_edit_role(): void
    {
        $admin = $this->createAdmin();
        $pendingUser = $this->createUser(['status' => 'pending', 'role' => 'afc_staff']);

        $response = $this->actingAs($admin)->post(route('admin.attendance.users.approve', $pendingUser));
        $response->assertRedirect();

        $pendingUser->refresh();
        $this->assertEquals('approved', $pendingUser->status);

        // Admin updates role to artsci_staff
        $updateResponse = $this->actingAs($admin)->put(route('admin.users.update', $pendingUser), [
            'name' => $pendingUser->name,
            'email' => $pendingUser->email,
            'role' => 'artsci_staff',
            'status' => 'approved',
        ]);

        $updateResponse->assertRedirect(route('admin.users.show', $pendingUser));
        $pendingUser->refresh();
        $this->assertEquals('artsci_staff', $pendingUser->role);
    }

    /** 4. Admin can approve pending device registration */
    public function test_admin_can_approve_pending_device_registration(): void
    {
        $admin = $this->createAdmin();
        $staff = $this->createUser(['status' => 'pending']);
        $this->createApprovedNetwork('127.0.0.1/32');

        $pendingCred = $this->registerCredentialForUser($staff, 'cred_unapproved_123', 'pending');
        $staff->passkeys()->create([
            'name' => 'Staff phone',
            'credential_id' => 'cred_unapproved_123',
            'credential' => [],
        ]);

        $approveResponse = $this->actingAs($admin)->post(route('admin.attendance.credentials.approve', $pendingCred));
        $approveResponse->assertRedirect();

        $pendingCred->refresh();
        $this->assertEquals('approved', $pendingCred->approval_status);
        $this->assertSame('approved', $staff->fresh()->status);

        session(['staff_passkey_credential_id' => $pendingCred->id]);

        $checkInSuccess = $this->actingAs($staff->fresh())
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('attendance.check-in'), []);

        $checkInSuccess->assertStatus(200);
        $checkInSuccess->assertJson(['success' => true]);
    }

    /** 5. Staff can view attendance dashboard */
    public function test_staff_can_view_attendance_dashboard(): void
    {
        $staff = $this->createUser();

        $response = $this->actingAs($staff)->get(route('attendance.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Staff Attendance');
        $response->assertSee('John');
    }

    /** 6. Staff can check in with valid credentials and approved network */
    public function test_staff_can_check_in_with_valid_credentials_and_approved_network(): void
    {
        $staff = $this->createUser();
        $this->createApprovedNetwork('127.0.0.1/32');
        $cred = $staff->attendanceCredentials()->firstOrFail();

        $response = $this->actingAs($staff)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('attendance.check-in'), []);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $staff->id,
            'check_in_credential_id' => $cred->id,
            'check_in_network_verified' => true,
        ]);
    }

    /** 7. Attendance uses the verified session device, not a client-supplied ID */
    public function test_checkin_uses_the_verified_session_device_not_a_client_supplied_id(): void
    {
        $staff = $this->createUser();
        $this->createApprovedNetwork('127.0.0.1/32');
        $cred = $staff->attendanceCredentials()->firstOrFail();

        $response = $this->actingAs($staff)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('attendance.check-in'), [
                'credential_id' => 'forged-device-id',
            ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $staff->id,
            'check_in_credential_id' => $cred->id,
        ]);
    }

    /** 7. Admin can view staff analytics */
    public function test_admin_can_view_staff_analytics(): void
    {
        $admin = $this->createAdmin();
        $staff = $this->createUser(['name' => 'Alice Late Staff']);

        $response = $this->actingAs($admin)->get(route('admin.attendance.analytics'));

        $response->assertStatus(200);
        $response->assertSee('Staff Attendance Analytics');
        $response->assertSee('Alice Late Staff');
    }

    /** 8. Staff can view personal attendance history */
    public function test_staff_can_view_personal_attendance_history(): void
    {
        $staff = $this->createUser();
        AttendanceRecord::create([
            'user_id' => $staff->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'check_in_at' => now(),
            'status' => 'present',
        ]);

        $response = $this->actingAs($staff)->get(route('attendance.history'));

        $response->assertStatus(200);
        $response->assertSee('Attendance History');
    }
}
