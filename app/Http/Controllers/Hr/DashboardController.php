<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HrDepartment;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total' => Employee::count(),
            'active' => Employee::where('employment_status', 'active')->count(),
            'artsci' => Employee::where('company', 'ARTSCI')->count(),
            'afc' => Employee::where('company', 'AFC')->count(),
            'probation' => Employee::where('confirmation_status', 'probation')->count(),
            'inactive' => Employee::whereIn('employment_status', ['inactive', 'terminated', 'resigned'])->count(),
        ];

        $recentEmployees = Employee::with(['department', 'position'])
            ->latest()
            ->limit(8)
            ->get();

        $companyDistribution = Employee::selectRaw('company, COUNT(*) as total')
            ->groupBy('company')
            ->pluck('total', 'company');

        $departmentDistribution = HrDepartment::withCount('employees')
            ->orderByDesc('employees_count')
            ->get()
            ->filter(fn (HrDepartment $department) => $department->employees_count > 0)
            ->take(10);

        return view('admin.hr.dashboard', compact(
            'stats', 'recentEmployees', 'companyDistribution', 'departmentDistribution'
        ));
    }
}
