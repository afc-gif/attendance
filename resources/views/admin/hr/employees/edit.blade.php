@extends('layouts.admin')
@section('title', 'Edit Employee')
@section('content')
<div class="max-w-5xl space-y-6">
    <div><h1 class="text-2xl font-extrabold text-slate-900">Edit Employee</h1><p class="text-xs text-slate-500">Permanent Employee ID: {{ $employee->employee_id }}</p></div>
    <form method="POST" action="{{ route('admin.hr.employees.update', $employee) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf @method('PUT')
        @include('admin.hr.employees._form')
    </form>
</div>
@endsection
