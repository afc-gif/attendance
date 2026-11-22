<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\HrDepartment;
use App\Models\HrPosition;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function index()
    {
        $departments = HrDepartment::withCount('employees')->orderBy('company')->orderBy('name')->get();
        $positions = HrPosition::with('department')->withCount('employees')->orderBy('company')->orderBy('title')->get();

        return view('admin.hr.organization.index', compact('departments', 'positions'));
    }

    public function storeDepartment(Request $request)
    {
        $validated = $request->validate([
            'company' => ['required', Rule::in(['ARTSCI', 'AFC'])],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['is_active'] = true;
        HrDepartment::create($validated);

        return back()->with('success', 'Department created successfully.');
    }

    public function updateDepartment(Request $request, HrDepartment $department)
    {
        $validated = $request->validate([
            'company' => ['required', Rule::in(['ARTSCI', 'AFC'])],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $department->update($validated);

        return back()->with('success', 'Department updated successfully.');
    }

    public function toggleDepartment(HrDepartment $department)
    {
        $department->update(['is_active' => ! $department->is_active]);

        return back()->with('success', 'Department status updated.');
    }

    public function storePosition(Request $request)
    {
        $validated = $request->validate([
            'company' => ['required', Rule::in(['ARTSCI', 'AFC'])],
            'department_id' => ['nullable', 'integer', 'exists:hr_departments,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $validated['is_active'] = true;
        HrPosition::create($validated);

        return back()->with('success', 'Position created successfully.');
    }

    public function updatePosition(Request $request, HrPosition $position)
    {
        $validated = $request->validate([
            'company' => ['required', Rule::in(['ARTSCI', 'AFC'])],
            'department_id' => ['nullable', 'integer', 'exists:hr_departments,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $position->update($validated);

        return back()->with('success', 'Position updated successfully.');
    }

    public function togglePosition(HrPosition $position)
    {
        $position->update(['is_active' => ! $position->is_active]);

        return back()->with('success', 'Position status updated.');
    }
}
