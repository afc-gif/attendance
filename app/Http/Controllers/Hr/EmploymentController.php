<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmploymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::with(['department', 'position']);

        if ($request->filled('view')) {
            match ($request->input('view')) {
                'probation' => $query->where('confirmation_status', 'probation'),
                'confirmed' => $query->where('confirmation_status', 'confirmed'),
                'inactive' => $query->whereIn('employment_status', ['inactive', 'terminated', 'resigned']),
                default => null,
            };
        }

        $employees = $query->orderBy('probation_end_date')->paginate(20)->appends($request->query());

        $approaching = Employee::where('confirmation_status', 'probation')
            ->whereBetween('probation_end_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->orderBy('probation_end_date')
            ->get();

        return view('admin.hr.employment.index', compact('employees', 'approaching'));
    }
}
