<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\HrDepartment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_only_super_admin_can_access_hr_routes(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);

        $this->actingAs($admin)
            ->get(route('admin.hr.dashboard'))
            ->assertForbidden();

        $this->actingAs($this->superAdmin())
            ->get(route('admin.hr.dashboard'))
            ->assertOk();
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
}
