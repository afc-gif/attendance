<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\HrDepartment;
use App\Models\HrPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HrManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::factory()->create([
            'role' => 'super_admin',
            'status' => 'approved',
        ]);
    }

    public function test_only_super_admin_and_hr_can_access_hr_routes(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
        /** @var User $hr */
        $hr = User::factory()->create(['role' => 'hr', 'status' => 'approved']);

        $this->actingAs($admin)
            ->get(route('admin.hr.dashboard'))
            ->assertForbidden();

        $this->actingAs($this->superAdmin())
            ->get(route('admin.hr.dashboard'))
            ->assertOk();

        $this->actingAs($hr)
            ->get(route('admin.hr.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Overview')
            ->assertSee('Staff Analytics & Trends', false)
            ->assertSee('Payroll & Salary', false)
            ->assertDontSee(route('admin.users.index'), false)
            ->assertDontSee('Deployment Monitor');

        $this->actingAs($hr)
            ->get(route('admin.attendance.index'))
            ->assertOk();

        $this->actingAs($hr)
            ->get(route('admin.payroll.index'))
            ->assertOk();

        $this->actingAs($hr)
            ->get(route('admin.shifts.index'))
            ->assertOk();

        $this->actingAs($hr)
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs($hr)
            ->get(route('admin.deployments.index'))
            ->assertForbidden();

        /** @var User $staff */
        $staff = User::factory()->create(['role' => 'artsci_staff', 'status' => 'approved']);
        $this->actingAs($staff)
            ->get(route('admin.payroll.index'))
            ->assertForbidden();

        $this->actingAs($hr)
            ->get(route('attendance.dashboard'))
            ->assertOk();
    }

    public function test_hr_role_login_redirects_directly_to_hr_panel(): void
    {
        /** @var User $hr */
        $hr = User::factory()->create([
            'email' => 'hr@example.test',
            'password' => Hash::make('HR password 123!'),
            'role' => 'hr',
            'status' => 'approved',
        ]);

        $this->post(route('login'), [
            'email' => $hr->email,
            'password' => 'HR password 123!',
        ])->assertRedirect(route('admin.hr.dashboard'));

        $this->assertAuthenticatedAs($hr);
    }

    public function test_only_super_admin_can_assign_or_manage_the_hr_role(): void
    {
        $superAdmin = $this->superAdmin();
        /** @var User $staff */
        $staff = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
        /** @var User $hr */
        $hr = User::factory()->create(['role' => 'hr', 'status' => 'approved']);

        $this->actingAs($superAdmin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('HR (HR Management Only)');

        $this->actingAs($superAdmin)
            ->put(route('admin.users.update', $staff), [
                'name' => $staff->name,
                'email' => $staff->email,
                'role' => 'hr',
                'status' => 'approved',
                'password' => '',
                'password_confirmation' => '',
            ])->assertRedirect(route('admin.users.show', $staff));

        $this->assertDatabaseHas('users', ['id' => $staff->id, 'role' => 'hr']);

        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
        $this->actingAs($admin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertDontSee('HR (HR Management Only)');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Another HR',
                'email' => 'another.hr@example.test',
                'password' => 'HR password 123!',
                'password_confirmation' => 'HR password 123!',
                'role' => 'hr',
                'status' => 'approved',
            ])->assertSessionHasErrors('role');

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $hr))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.users.show', $hr))
            ->assertOk()
            ->assertDontSee('Change Role &amp; Edit Profile')
            ->assertDontSee('Suspend Account');
    }

    public function test_super_admin_can_create_employee_and_generate_company_specific_ids(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)->post(route('admin.hr.employees.store'), [
            'company' => 'ARTSCI',
            'first_name' => 'Ada',
            'middle_name' => 'Grace',
            'last_name' => 'Lovelace',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'confirmation_status' => 'probation',
        ])->assertRedirect();

        $this->actingAs($superAdmin)->post(route('admin.hr.employees.store'), [
            'company' => 'ARTSCI',
            'first_name' => 'Alan',
            'last_name' => 'Turing',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'confirmation_status' => 'confirmed',
        ])->assertRedirect();

        $this->actingAs($superAdmin)->post(route('admin.hr.employees.store'), [
            'company' => 'AFC',
            'first_name' => 'Katherine',
            'last_name' => 'Johnson',
            'employment_type' => 'contract',
            'employment_status' => 'active',
            'confirmation_status' => 'probation',
        ])->assertRedirect();

        $this->assertDatabaseHas('employees', ['employee_id' => 'ART-000001', 'company' => 'ARTSCI']);
        $this->assertDatabaseHas('employees', ['employee_id' => 'ART-000002', 'company' => 'ARTSCI']);
        $this->assertDatabaseHas('employees', ['employee_id' => 'AFC-000001', 'company' => 'AFC']);
    }

    public function test_hr_employee_id_creates_a_pending_account_without_email_invitation(): void
    {
        Mail::fake();
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)->post(route('admin.hr.employees.store'), [
            'company' => 'AFC',
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'confirmation_status' => 'probation',
        ])->assertRedirect();

        $employee = Employee::where('employee_id', 'AFC-000001')->firstOrFail();
        $this->assertNotNull($employee->user);
        $this->assertSame('afc_staff', $employee->user->role);
        $this->assertSame('pending', $employee->user->status);
        $this->assertSame('afc-000001@staff.invalid', $employee->user->email);
        Mail::assertNothingOutgoing();
    }

    public function test_super_admin_can_load_employee_creation_form(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('admin.hr.employees.create'))
            ->assertOk()
            ->assertSee('Personal Information');
    }

    public function test_employee_profile_edit_search_and_department_management_work(): void
    {
        $superAdmin = $this->superAdmin();
        $department = HrDepartment::create([
            'company' => 'ARTSCI',
            'name' => 'Engineering',
            'is_active' => true,
        ]);

        $employee = Employee::create([
            'employee_id' => 'ART-000001',
            'company' => 'ARTSCI',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'department_id' => $department->id,
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'confirmation_status' => 'confirmed',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.hr.employees.index', ['search' => 'Ada']))
            ->assertOk()
            ->assertSee('Ada Lovelace');

        $this->actingAs($superAdmin)
            ->get(route('admin.hr.employees.show', $employee))
            ->assertOk()
            ->assertSee('ART-000001');

        $this->actingAs($superAdmin)
            ->put(route('admin.hr.employees.update', $employee), [
                'company' => 'ARTSCI',
                'first_name' => 'Augusta',
                'last_name' => 'Lovelace',
                'department_id' => $department->id,
                'employment_type' => 'full_time',
                'employment_status' => 'active',
                'confirmation_status' => 'confirmed',
            ])->assertRedirect();

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'first_name' => 'Augusta',
        ]);
    }

    public function test_document_upload_is_private_and_download_requires_hr_authorization(): void
    {
        Storage::fake('local');
        $superAdmin = $this->superAdmin();
        $employee = Employee::create([
            'employee_id' => 'ART-000001',
            'company' => 'ARTSCI',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'confirmation_status' => 'probation',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.hr.documents.store', $employee), [
                'document_type' => 'identification',
                'document' => UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'),
            ])->assertRedirect();

        /** @var EmployeeDocument $document */
        $document = $employee->documents()->first();
        $this->assertNotNull($document);
        $this->assertTrue(Storage::disk('local')->exists($document->stored_name));

        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->get(route('admin.hr.documents.download', $document))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get(route('admin.hr.documents.download', $document))
            ->assertOk();
    }

    public function test_csv_export_returns_download(): void
    {
        $superAdmin = $this->superAdmin();
        Employee::create([
            'employee_id' => 'AFC-000001',
            'company' => 'AFC',
            'first_name' => 'Katherine',
            'last_name' => 'Johnson',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'confirmation_status' => 'confirmed',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.hr.reports.export'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_super_admin_can_upload_replace_and_view_passport_photograph(): void
    {
        Storage::fake('local');
        $superAdmin = $this->superAdmin();
        $employee = $this->createCardEligibleEmployee();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $this->actingAs($superAdmin)
            ->post(route('admin.hr.employees.passport.upload', $employee), [
                'passport_photo' => UploadedFile::fake()->createWithContent('passport.png', $png),
            ])
            ->assertRedirect();

        $employee->refresh();
        $firstPath = $employee->passport_photo_path;
        $this->assertNotNull($firstPath);
        $this->assertTrue(Storage::disk('local')->exists($firstPath));

        $this->actingAs($superAdmin)
            ->post(route('admin.hr.employees.passport.upload', $employee), [
                'passport_photo' => UploadedFile::fake()->createWithContent('replacement.png', $png),
            ])
            ->assertRedirect();

        $employee->refresh();
        $this->assertNotSame($firstPath, $employee->passport_photo_path);
        $this->assertTrue(! Storage::disk('local')->exists($firstPath));
        $this->assertTrue(Storage::disk('local')->exists($employee->passport_photo_path));

        $this->actingAs($superAdmin)
            ->get(route('admin.hr.employees.passport.show', $employee))
            ->assertOk()
            ->assertHeader('cache-control');
    }

    public function test_passport_rejects_invalid_image_and_authorization(): void
    {
        Storage::fake('local');
        $superAdmin = $this->superAdmin();
        $employee = $this->createCardEligibleEmployee();

        $this->actingAs($superAdmin)
            ->post(route('admin.hr.employees.passport.upload', $employee), [
                'passport_photo' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            ])
            ->assertSessionHasErrors('passport_photo');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $this->actingAs($superAdmin)
            ->post(route('admin.hr.employees.passport.upload', $employee), [
                'passport_photo' => UploadedFile::fake()->createWithContent('large.png', str_repeat('x', 2100 * 1024).$png),
            ])
            ->assertSessionHasErrors('passport_photo');

        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->get(route('admin.hr.employees.passport.show', $employee))
            ->assertForbidden();
    }

    public function test_id_card_preview_and_pdf_support_both_company_brands(): void
    {
        Storage::fake('local');
        $superAdmin = $this->superAdmin();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        foreach (['ARTSCI', 'AFC'] as $company) {
            $employee = $this->createCardEligibleEmployee($company);
            $employee->update(['passport_photo_path' => 'hr/employees/'.$employee->employee_id.'/passport/test.png']);
            Storage::disk('local')->put($employee->passport_photo_path, $png);

            $this->actingAs($superAdmin)
                ->post(route('admin.hr.employees.id-card.generate', $employee))
                ->assertRedirect(route('admin.hr.employees.id-card.preview', $employee));

            $this->actingAs($superAdmin)
                ->get(route('admin.hr.employees.id-card.preview', $employee))
                ->assertOk()
                ->assertSee($employee->full_name)
                ->assertSee($employee->employee_id);

            $this->actingAs($superAdmin)
                ->get(route('admin.hr.employees.id-card.download', $employee))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_id_card_requires_photo_department_position_and_is_authorized(): void
    {
        $superAdmin = $this->superAdmin();
        $employee = Employee::create([
            'employee_id' => 'ART-000999',
            'company' => 'ARTSCI',
            'first_name' => 'Missing',
            'last_name' => 'Data',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'confirmation_status' => 'probation',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.hr.employees.id-card.generate', $employee))
            ->assertStatus(422);

        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->post(route('admin.hr.employees.id-card.generate', $employee))
            ->assertForbidden();
    }

    protected function createCardEligibleEmployee(string $company = 'ARTSCI'): Employee
    {
        $department = HrDepartment::create([
            'company' => $company,
            'name' => $company.' Engineering',
            'is_active' => true,
        ]);
        $position = HrPosition::create([
            'company' => $company,
            'department_id' => $department->id,
            'title' => 'Software Engineer',
            'is_active' => true,
        ]);

        return Employee::create([
            'employee_id' => $company === 'ARTSCI' ? 'ART-000100' : 'AFC-000100',
            'company' => $company,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'confirmation_status' => 'confirmed',
            'passport_photo_mime' => 'image/png',
        ]);
    }
}
