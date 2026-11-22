<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeEmploymentHistory;
use App\Models\HrDepartment;
use App\Models\HrPosition;
use App\Models\User;
use App\Services\EmployeeIdService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::with(['department', 'position', 'user']);

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($company = $request->input('company')) {
            $query->where('company', $company);
        }
        if ($department = $request->input('department_id')) {
            $query->where('department_id', $department);
        }
        if ($status = $request->input('employment_status')) {
            $query->where('employment_status', $status);
        }

        $employees = $query->latest()->paginate(20)->appends($request->query());
        $departments = HrDepartment::where('is_active', true)->orderBy('name')->get();

        return view('admin.hr.employees.index', compact('employees', 'departments'));
    }

    public function create()
    {
        return view('admin.hr.employees.create', $this->formOptions());
    }

    public function store(Request $request, EmployeeIdService $employeeIds)
    {
        $validated = $this->validateEmployee($request);

        $employee = DB::transaction(function () use ($validated, $employeeIds) {
            $validated['employee_id'] = $employeeIds->generate($validated['company']);

            $employee = Employee::create($validated);
            $this->syncEmergencyContact($employee, $validated);
            EmployeeEmploymentHistory::create([
                'employee_id' => $employee->id,
                'company' => $employee->company,
                'department_id' => $employee->department_id,
                'position_id' => $employee->position_id,
                'effective_date' => $employee->employment_date ?: now()->toDateString(),
                'event_type' => 'created',
                'notes' => 'Employee record created',
                'created_by' => Auth::id(),
            ]);
            $this->storeEmployeeDocuments($employee, request());

            return $employee;
        });

        return redirect()->route('admin.hr.employees.show', $employee)
            ->with('success', "Employee {$employee->employee_id} created successfully.");
    }

    public function show(Employee $employee)
    {
        $employee->load(['department', 'position', 'reportingManager', 'user', 'documents', 'emergencyContacts', 'employmentHistories']);

        return view('admin.hr.employees.show', compact('employee'));
    }

    public function edit(Employee $employee)
    {
        $employee->load('emergencyContacts');

        return view('admin.hr.employees.edit', array_merge(
            ['employee' => $employee],
            $this->formOptions($employee)
        ));
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $this->validateEmployee($request, $employee);

        DB::transaction(function () use ($employee, $validated) {
            $oldDepartment = $employee->department_id;
            $oldPosition = $employee->position_id;
            $employee->update($validated);
            $this->syncEmergencyContact($employee, $validated);
            $this->storeEmployeeDocuments($employee, request());

            if ($oldDepartment != $employee->department_id || $oldPosition != $employee->position_id) {
                EmployeeEmploymentHistory::create([
                    'employee_id' => $employee->id,
                    'company' => $employee->company,
                    'department_id' => $employee->department_id,
                    'position_id' => $employee->position_id,
                    'effective_date' => now()->toDateString(),
                    'event_type' => 'assignment_changed',
                    'notes' => 'Department or position changed',
                    'created_by' => Auth::id(),
                ]);
            }
        });

        return redirect()->route('admin.hr.employees.show', $employee)
            ->with('success', 'Employee record updated successfully.');
    }

    public function destroy(Employee $employee)
    {
        $employeeId = $employee->employee_id;
        $employee->delete();

        return redirect()->route('admin.hr.employees.index')
            ->with('success', "Employee {$employeeId} has been deactivated.");
    }

    protected function validateEmployee(Request $request, ?Employee $employee = null): array
    {
        $validated = $request->validate([
            'company' => ['required', Rule::in(['ARTSCI', 'AFC'])],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:30'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'residential_address' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'religion' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', 'string', 'max:50'],
            'next_of_kin' => ['nullable', 'string', 'max:255'],
            'next_of_kin_relationship' => ['nullable', 'string', 'max:100'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:50'],
            'next_of_kin_address' => ['nullable', 'string', 'max:2000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer', 'exists:hr_departments,id'],
            'position_id' => ['nullable', 'integer', 'exists:hr_positions,id'],
            'reporting_manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('employees', 'user_id')->ignore($employee?->id)],
            'employment_type' => ['required', 'string', 'max:50'],
            'employment_date' => ['nullable', 'date'],
            'probation_start_date' => ['nullable', 'date'],
            'probation_end_date' => ['nullable', 'date', 'after_or_equal:probation_start_date'],
            'employment_status' => ['required', Rule::in(['active', 'inactive', 'terminated', 'resigned'])],
            'confirmation_status' => ['required', Rule::in(['pending', 'probation', 'confirmed'])],
        ]);

        return $validated;
    }

    protected function formOptions(?Employee $employee = null): array
    {
        return [
            'departments' => HrDepartment::where('is_active', true)->orderBy('company')->orderBy('name')->get(),
            'positions' => HrPosition::where('is_active', true)->orderBy('company')->orderBy('title')->get(),
            'managers' => Employee::when($employee, fn ($q) => $q->where('id', '!=', $employee->id))->orderBy('first_name')->get(),
            'users' => User::whereDoesntHave('hrEmployee')->orderBy('name')->get(),
        ];
    }

    protected function syncEmergencyContact(Employee $employee, array $validated): void
    {
        if (empty($validated['emergency_contact_name']) && empty($validated['emergency_contact_phone'])) {
            return;
        }

        $employee->emergencyContacts()->updateOrCreate(
            ['is_primary' => true],
            [
                'name' => $validated['emergency_contact_name'] ?: $validated['next_of_kin'],
                'phone' => $validated['emergency_contact_phone'] ?: $validated['next_of_kin_phone'],
                'relationship' => $validated['emergency_contact_relationship'],
                'address' => $validated['next_of_kin_address'],
            ]
        );
    }

    protected function storeEmployeeDocuments(Employee $employee, Request $request): void
    {
        $rules = [
            'passport_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
            'cv' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'certificates' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'identification_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'employment_letter' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'supporting_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];

        $validated = $request->validate($rules);

        foreach ($validated as $type => $file) {
            if (! $file) {
                continue;
            }

            $documentType = str_replace('_', '-', $type);
            $storedName = $file->store('hr-documents/'.$employee->employee_id, 'local');

            if (! $storedName) {
                throw new \RuntimeException('Unable to store employee document.');
            }

            EmployeeDocument::create([
                'employee_id' => $employee->id,
                'document_type' => $documentType,
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => $storedName,
                'disk' => 'local',
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
                'uploaded_by' => Auth::id(),
            ]);
        }
    }
}
