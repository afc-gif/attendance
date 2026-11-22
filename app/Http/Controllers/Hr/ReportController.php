<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HrDepartment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index()
    {
        $byCompany = Employee::selectRaw('company, COUNT(*) as total')
            ->groupBy('company')->pluck('total', 'company');
        $byDepartment = HrDepartment::withCount('employees')
            ->orderByDesc('employees_count')
            ->get()
            ->filter(fn (HrDepartment $department) => $department->employees_count > 0);
        $byStatus = Employee::selectRaw('employment_status, COUNT(*) as total')
            ->groupBy('employment_status')->pluck('total', 'employment_status');
        $probation = [
            'probation' => Employee::where('confirmation_status', 'probation')->count(),
            'confirmed' => Employee::where('confirmation_status', 'confirmed')->count(),
            'pending' => Employee::where('confirmation_status', 'pending')->count(),
        ];

        return view('admin.hr.reports.index', compact('byCompany', 'byDepartment', 'byStatus', 'probation'));
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Employee::with(['department', 'position']);
        if ($request->filled('company')) {
            $query->where('company', $request->company);
        }
        if ($request->filled('employment_status')) {
            $query->where('employment_status', $request->employment_status);
        }
        if ($request->filled('confirmation_status')) {
            $query->where('confirmation_status', $request->confirmation_status);
        }

        $filename = 'hr-employees-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Employee ID', 'Company', 'First Name', 'Middle Name', 'Last Name',
                'Department', 'Position', 'Employment Type', 'Employment Date',
                'Probation End Date', 'Employment Status', 'Confirmation Status', 'Email', 'Phone',
            ]);
            $query->orderBy('employee_id')->chunk(500, function ($employees) use ($handle) {
                foreach ($employees as $employee) {
                    fputcsv($handle, [
                        $employee->employee_id,
                        $employee->company,
                        $employee->first_name,
                        $employee->middle_name,
                        $employee->last_name,
                        $employee->department?->name,
                        $employee->position?->title,
                        $employee->employment_type,
                        $employee->employment_date?->format('Y-m-d'),
                        $employee->probation_end_date?->format('Y-m-d'),
                        $employee->employment_status,
                        $employee->confirmation_status,
                        $employee->email,
                        $employee->phone,
                    ]);
                }
            });
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
