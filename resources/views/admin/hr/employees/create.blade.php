@extends('layouts.admin')
@section('title', 'Add Employee')
@section('content')
<div class="max-w-5xl space-y-6">
    <div><h1 class="text-2xl font-extrabold text-slate-900">Add Employee</h1><p class="text-xs text-slate-500">Employee ID will be generated automatically.</p></div>
    <form method="POST" action="{{ route('admin.hr.employees.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @include('admin.hr.employees._form')
    </form>
</div>
@endsection
