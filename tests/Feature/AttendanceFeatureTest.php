<?php

namespace Tests\Feature;

use App\Models\AttendanceAuditLog;
use App\Models\AttendanceCredential;
use App\Models\AttendanceNetwork;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttendanceFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'John Staff',
            'email' => 'staff_' . Str::random(6) . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'afc_staff',
            'status' => 'approved',
        ], $overrides));
    }

    protected function createAdmin(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Admin Manager',
            'email' => 'admin_' . Str::random(6) . '@example.com',
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

    protected function registerCredentialForUser(User $user, string $credentialId = 'cred_john_123', string $approvalStatus = 'approved'): AttendanceCredential
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

    /** 1. Staff registration requires an invitation */
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
        $response->assertSessionHas('status', 'Staff accounts are invitation-only. Contact HR for an activation link.');
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

    /** 3. Device passkey registration starts in pending approval status */
    public function test_device_registration_starts_in_pending_approval_status(): void
    {
        $staff = $this->createUser();

        $challenge = 'test_challenge_123';
        session(['webauthn_challenge' => $challenge]);
        $clientDataJson = \App\Services\WebAuthnService::base64UrlEncode(json_encode([
            'challenge' => \App\Services\WebAuthnService::base64UrlEncode($challenge)
        ]));

        $response = $this->actingAs($staff)->postJson(route('attendance.register-device'), [
            'credential_id' => 'cred_pending_123',
            'public_key' => 'pubkey_xyz',
            'device_name' => "Staff Mobile Phone",
            'client_data_json' => $clientDataJson,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('attendance_credentials', [
            'user_id' => $staff->id,
            'credential_id' => 'cred_pending_123',
            'approval_status' => 'pending',
        ]);
    }

    /** 4. Admin can approve pending device registration */
    public function test_admin_can_approve_pending_device_registration(): void
    {
        $admin = $this->createAdmin();
        $staff = $this->createUser();
        $this->createApprovedNetwork('127.0.0.1/32');

        $pendingCred = $this->registerCredentialForUser($staff, 'cred_unapproved_123', 'pending');

        $approveResponse = $this->actingAs($admin)->post(route('admin.attendance.credentials.approve', $pendingCred));
        $approveResponse->assertRedirect();

        $pendingCred->refresh();
        $this->assertEquals('approved', $pendingCred->approval_status);

        $challenge = 'test_challenge_123';
        session(['webauthn_challenge' => $challenge]);
        $clientDataJson = \App\Services\WebAuthnService::base64UrlEncode(json_encode([
            'challenge' => \App\Services\WebAuthnService::base64UrlEncode($challenge)
        ]));

        $checkInSuccess = $this->actingAs($staff->fresh())
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('attendance.check-in'), [
                'credential_id' => 'cred_unapproved_123',
                'client_data_json' => $clientDataJson,
            ]);

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
        $cred = $this->registerCredentialForUser($staff, 'cred_john_passkey', 'approved');

        $challenge = 'test_challenge_123';
        session(['webauthn_challenge' => $challenge]);
        $clientDataJson = \App\Services\WebAuthnService::base64UrlEncode(json_encode([
            'challenge' => \App\Services\WebAuthnService::base64UrlEncode($challenge)
        ]));

        $response = $this->actingAs($staff)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('attendance.check-in'), [
                'credential_id' => 'cred_john_passkey',
                'client_data_json' => $clientDataJson,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $staff->id,
            'check_in_credential_id' => $cred->id,
            'check_in_network_verified' => true,
        ]);
    }

    /** 7. Canceling device passkey prompt fails check-in */
    public function test_canceling_device_passkey_prompt_fails_checkin(): void
    {
        $staff = $this->createUser();
        $this->createApprovedNetwork('127.0.0.1/32');
        $cred = $this->registerCredentialForUser($staff, 'cred_john_passkey', 'approved');

        $response = $this->actingAs($staff)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('attendance.check-in'), [
                'credential_id' => 'cred_john_passkey',
                'client_data_json' => '', // User canceled prompt
            ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
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
