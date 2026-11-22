<?php

namespace Tests\Feature;

use App\Models\AttendanceCredential;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\TestCase;

class StaffPasskeyLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function createStaff(string $status = 'pending'): array
    {
        $user = User::create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'password' => Hash::make('A secure password 123!'),
            'role' => 'artsci_staff',
            'status' => $status,
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'employee_id' => 'ART-000001',
            'company' => 'ARTSCI',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'confirmation_status' => 'confirmed',
        ]);

        return [$user, $employee];
    }

    protected function createDevice(User $user, string $credentialId, string $approvalStatus): AttendanceCredential
    {
        $user->passkeys()->create([
            'name' => 'Staff phone',
            'credential_id' => $credentialId,
            'credential' => [],
        ]);

        return AttendanceCredential::create([
            'user_id' => $user->id,
            'credential_id' => $credentialId,
            'public_key' => '{}',
            'attestation_format' => 'webauthn',
            'device_name' => 'Staff phone',
            'is_active' => true,
            'approval_status' => $approvalStatus,
            'registered_at' => now(),
        ]);
    }

    public function test_login_page_has_employee_device_flow_and_separate_admin_login(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Employee ID')
            ->assertSee('Register this device')
            ->assertSee('Administrator / HR sign-in');
    }

    public function test_hr_employee_id_can_start_first_device_registration(): void
    {
        [, $employee] = $this->createStaff();

        $this->postJson(route('staff.passkeys.register.options'), [
            'employee_id' => $employee->employee_id,
        ])->assertOk()
            ->assertJsonStructure([
                'options' => ['challenge', 'rp' => ['id', 'name'], 'user' => ['id', 'name', 'displayName']],
            ]);

        $this->assertSame(
            $employee->user_id,
            session('passkey.registration_user_id'),
        );
    }

    public function test_unknown_employee_id_cannot_start_device_registration(): void
    {
        $this->postJson(route('staff.passkeys.register.options'), [
            'employee_id' => 'ART-999999',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');
    }

    public function test_authenticated_staff_without_an_approved_passkey_session_cannot_open_dashboard(): void
    {
        [$user] = $this->createStaff('approved');

        $this->actingAs($user)
            ->get(route('attendance.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_forged_device_registration_is_rejected_without_creating_a_passkey(): void
    {
        [, $employee] = $this->createStaff();
        $this->postJson(route('staff.passkeys.register.options'), [
            'employee_id' => $employee->employee_id,
        ])->assertOk();

        $credentialId = Base64UrlSafe::encodeUnpadded('forged-device-credential');
        $clientData = Base64UrlSafe::encodeUnpadded(json_encode([
            'type' => 'webauthn.create',
            'challenge' => Base64UrlSafe::encodeUnpadded('not-the-issued-challenge'),
            'origin' => 'http://localhost',
        ], JSON_THROW_ON_ERROR));

        $this->postJson(route('staff.passkeys.register'), [
            'name' => 'Forged device',
            'credential' => [
                'id' => $credentialId,
                'rawId' => $credentialId,
                'type' => 'public-key',
                'response' => [
                    'clientDataJSON' => $clientData,
                    'attestationObject' => Base64UrlSafe::encodeUnpadded('not-a-valid-attestation'),
                ],
            ],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('passkeys', 0);
        $this->assertDatabaseCount('attendance_credentials', 0);
    }

    public function test_login_options_only_allow_active_approved_passkeys_for_that_employee(): void
    {
        [$user, $employee] = $this->createStaff('approved');
        $approved = $this->createDevice($user, Base64UrlSafe::encodeUnpadded('approved-credential'), 'approved');
        $this->createDevice($user, Base64UrlSafe::encodeUnpadded('pending-credential'), 'pending');

        $response = $this->postJson(route('staff.passkeys.login.options'), [
            'employee_id' => $employee->employee_id,
        ])->assertOk();

        $this->assertSame(
            [$approved->credential_id],
            collect($response->json('options.allowCredentials'))->pluck('id')->all(),
        );
        $this->assertSame($user->id, session('passkey.login_user_id'));
    }

    public function test_staff_cannot_use_email_and_password_to_sign_in_but_admins_can(): void
    {
        [$staff] = $this->createStaff('approved');

        $this->post(route('login'), [
            'email' => $staff->email,
            'password' => 'A secure password 123!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.test',
            'password' => Hash::make('Admin password 123!'),
            'role' => 'admin',
            'status' => 'approved',
        ]);

        $this->post(route('login'), [
            'email' => $admin->email,
            'password' => 'Admin password 123!',
        ])->assertRedirect(route('admin.attendance.index'));

        $this->assertAuthenticatedAs($admin);
    }
}
