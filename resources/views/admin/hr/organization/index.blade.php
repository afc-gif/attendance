@extends('layouts.admin')
@section('title', 'Departments & Positions')
@section('content')
<div class="space-y-6">
    <div><h1 class="text-2xl font-extrabold text-slate-900">Departments & Positions</h1><p class="text-xs text-slate-500">Company-specific organization structure</p></div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <h2 class="font-extrabold text-sm mb-4">Departments</h2>
            <form method="POST" action="{{ route('admin.hr.departments.store') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">@csrf<select name="company" required class="px-3 py-2 border border-slate-300 rounded-lg text-xs"><option value="ARTSCI">ARTSCI</option><option value="AFC">AFC</option></select><input name="name" required placeholder="Department name" class="px-3 py-2 border border-slate-300 rounded-lg text-xs"><input name="code" placeholder="Code" class="px-3 py-2 border border-slate-300 rounded-lg text-xs"><button class="bg-indigo-600 text-white rounded-lg text-xs font-bold">Add Department</button></form>
            <div class="divide-y divide-slate-100">@forelse($departments as $department)<div class="py-3 flex items-center justify-between"><div><div class="text-xs font-bold">{{ $department->name }}</div><div class="text-[10px] text-slate-400">{{ $department->company }} · {{ $department->employees_count }} employee(s)</div></div><form method="POST" action="{{ route('admin.hr.departments.toggle', $department) }}">@csrf @method('PATCH')<button class="text-xs font-bold {{ $department->is_active ? 'text-rose-600' : 'text-emerald-600' }}">{{ $department->is_active ? 'Deactivate' : 'Activate' }}</button></form></div>@empty<p class="text-xs text-slate-400">No departments.</p>@endforelse</div>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <h2 class="font-extrabold text-sm mb-4">Positions</h2>
            <form method="POST" action="{{ route('admin.hr.positions.store') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">@csrf<select name="company" required class="px-3 py-2 border border-slate-300 rounded-lg text-xs"><option value="ARTSCI">ARTSCI</option><option value="AFC">AFC</option></select><select name="department_id" class="px-3 py-2 border border-slate-300 rounded-lg text-xs"><option value="">No department</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->company }} · {{ $department->name }}</option>@endforeach</select><input name="title" required placeholder="Position title" class="px-3 py-2 border border-slate-300 rounded-lg text-xs"><button class="bg-indigo-600 text-white rounded-lg text-xs font-bold">Add Position</button></form>
            <div class="divide-y divide-slate-100">@forelse($positions as $position)<div class="py-3 flex items-center justify-between"><div><div class="text-xs font-bold">{{ $position->title }}</div><div class="text-[10px] text-slate-400">{{ $position->company }} · {{ $position->department?->name ?? 'No department' }} · {{ $position->employees_count }} employee(s)</div></div><form method="POST" action="{{ route('admin.hr.positions.toggle', $position) }}">@csrf @method('PATCH')<button class="text-xs font-bold {{ $position->is_active ? 'text-rose-600' : 'text-emerald-600' }}">{{ $position->is_active ? 'Deactivate' : 'Activate' }}</button></form></div>@empty<p class="text-xs text-slate-400">No positions.</p>@endforelse</div>
        </div>
    </div>
</div>
@endsection
