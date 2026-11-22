@extends('layouts.admin')

@section('title', 'HR Management Overview')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <p class="text-[10px] font-extrabold uppercase tracking-widest text-indigo-600">People Operations</p>
            <h1 class="text-2xl font-extrabold text-slate-900">HR Management Overview</h1>
        </div>
        <a href="{{ route('admin.hr.employees.create') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs py-2.5 px-4 rounded-xl shadow">Quick Add Employee</a>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
        @foreach([
            'Total Employees' => [$stats['total'], 'text-slate-900'],
            'Active' => [$stats['active'], 'text-emerald-600'],
            'ARTSCI' => [$stats['artsci'], 'text-purple-600'],
            'AFC' => [$stats['afc'], 'text-sky-600'],
            'On Probation' => [$stats['probation'], 'text-amber-600'],
            'Inactive' => [$stats['inactive'], 'text-rose-600'],
        ] as $label => $metric)
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">{{ $label }}</span>
                <span class="text-2xl font-black {{ $metric[1] }} mt-1 block">{{ $metric[0] }}</span>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 flex justify-between">
                <h2 class="font-extrabold text-sm">Recently Added Employees</h2>
                <a href="{{ route('admin.hr.employees.index') }}" class="text-xs font-bold text-indigo-600">View directory</a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($recentEmployees as $employee)
                    <a href="{{ route('admin.hr.employees.show', $employee) }}" class="p-4 flex items-center justify-between hover:bg-slate-50">
                        <div>
                            <div class="font-bold text-sm text-slate-900">{{ $employee->full_name }}</div>
                            <div class="text-xs text-slate-500">{{ $employee->employee_id }} · {{ $employee->company }} · {{ $employee->department?->name ?? 'No department' }}</div>
                        </div>
                        <span class="text-[10px] font-bold px-2 py-1 rounded-full {{ $employee->employment_status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ ucfirst($employee->employment_status) }}</span>
                    </a>
                @empty
                    <div class="p-8 text-center text-xs text-slate-400">No employees have been added yet.</div>
                @endforelse
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <h2 class="font-extrabold text-sm mb-3">Distribution by Company</h2>
                @foreach(['ARTSCI', 'AFC'] as $company)
                    <div class="flex items-center justify-between py-2 text-xs">
                        <span class="font-bold text-slate-600">{{ $company }}</span>
                        <span class="font-black text-slate-900">{{ $companyDistribution[$company] ?? 0 }}</span>
                    </div>
                @endforeach
            </div>
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <h2 class="font-extrabold text-sm mb-3">Department Distribution</h2>
                @forelse($departmentDistribution as $department)
                    <div class="flex items-center justify-between py-2 text-xs">
                        <span class="font-bold text-slate-600">{{ $department->name }}</span>
                        <span class="font-black text-slate-900">{{ $department->employees_count }}</span>
                    </div>
                @empty
                    <p class="text-xs text-slate-400">No department assignments yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
