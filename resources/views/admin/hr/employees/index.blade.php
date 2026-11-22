@extends('layouts.admin')
@section('title', 'Employee Directory')
@section('content')
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div><h1 class="text-2xl font-extrabold text-slate-900">Employee Directory</h1><p class="text-xs text-slate-500">ARTSCI and AFC employee records</p></div>
        <a href="{{ route('admin.hr.employees.create') }}" class="bg-indigo-600 text-white font-bold text-xs py-2.5 px-4 rounded-xl">Add Employee</a>
    </div>
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
        <form method="GET" class="flex flex-wrap gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or employee ID" class="flex-1 min-w-[220px] px-3 py-2 border border-slate-300 rounded-lg text-xs">
            <select name="company" class="px-3 py-2 border border-slate-300 rounded-lg text-xs"><option value="">All Companies</option>@foreach(['ARTSCI','AFC'] as $company)<option value="{{ $company }}" @selected(request('company') === $company)>{{ $company }}</option>@endforeach</select>
            <select name="department_id" class="px-3 py-2 border border-slate-300 rounded-lg text-xs"><option value="">All Departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->company }} · {{ $department->name }}</option>@endforeach</select>
            <select name="employment_status" class="px-3 py-2 border border-slate-300 rounded-lg text-xs"><option value="">All Statuses</option>@foreach(['active','inactive','terminated','resigned'] as $status)<option value="{{ $status }}" @selected(request('employment_status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
            <button class="bg-slate-900 text-white px-4 py-2 rounded-lg text-xs font-bold">Filter</button>
        </form>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto"><table class="w-full text-left text-xs">
            <thead class="bg-slate-100 text-[10px] uppercase text-slate-500"><tr><th class="p-3.5">Employee</th><th class="p-3.5">Company</th><th class="p-3.5">Department</th><th class="p-3.5">Position</th><th class="p-3.5">Status</th><th class="p-3.5 text-right">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($employees as $employee)
                <tr class="hover:bg-slate-50"><td class="p-3.5"><a href="{{ route('admin.hr.employees.show', $employee) }}" class="font-bold text-slate-900">{{ $employee->full_name }}</a><div class="text-slate-400">{{ $employee->employee_id }}</div></td><td class="p-3.5">{{ $employee->company }}</td><td class="p-3.5">{{ $employee->department?->name ?? '—' }}</td><td class="p-3.5">{{ $employee->position?->title ?? '—' }}</td><td class="p-3.5"><span class="px-2 py-1 rounded-full font-bold {{ $employee->employment_status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ ucfirst($employee->employment_status) }}</span></td><td class="p-3.5 text-right"><a href="{{ route('admin.hr.employees.edit', $employee) }}" class="text-indigo-600 font-bold">Edit</a></td></tr>
            @empty
                <tr><td colspan="6" class="p-10 text-center text-slate-400">No employees found.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="p-4 border-t border-slate-100">{{ $employees->links() }}</div>
    </div>
</div>
@endsection
