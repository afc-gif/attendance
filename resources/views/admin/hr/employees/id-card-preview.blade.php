@extends('layouts.admin')
@section('title', 'ID Card Preview - '.$employee->employee_id)
@push('styles')
<style>
.artsci-text { color: #35B3FF; }
.afc-text { color: #A37231; }
.artsci-button { background: #35B3FF; }
.afc-button { background: #A37231; }
.artsci-header { background: #0F172A; }
.afc-header { background: #111827; }
.artsci-photo { background: #35B3FF; }
.afc-photo { background: #A37231; }
</style>
@endpush
@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><p class="text-xs font-bold {{ $branding['css_class'] }}-text">{{ $employee->employee_id }}</p><h1 class="text-2xl font-extrabold text-slate-900">Employee ID Card Preview</h1></div>
        <div class="flex gap-2">
            <a href="{{ route('admin.hr.employees.show', $employee) }}" class="px-4 py-2.5 rounded-xl border border-slate-300 text-xs font-bold">Back to Employee</a>
            <a href="{{ route('admin.hr.employees.id-card.download', $employee) }}" class="px-4 py-2.5 rounded-xl text-white text-xs font-bold {{ $branding['css_class'] }}-button">Download PDF</a>
        </div>
    </div>
    <div class="flex justify-center">
        <div class="w-[323px] h-[204px] rounded-2xl overflow-hidden shadow-2xl border border-slate-200 bg-white">
            <div class="h-14 px-4 flex items-center justify-between {{ $branding['css_class'] }}-header">
                @if($branding['logo'])
                    <img src="{{ $branding['logo'] }}" alt="{{ $branding['name'] }} logo" class="h-11 max-w-16 object-contain">
                @endif
                <div class="text-right text-white"><div class="text-sm font-black">{{ $branding['name'] }}</div><div class="text-[8px] uppercase tracking-widest">Employee ID</div></div>
            </div>
            <div class="h-[150px] flex">
                <div class="w-[112px] p-3 flex items-center justify-center {{ $branding['css_class'] }}-photo">
                    @if($photoDataUri)
                        <img src="{{ $photoDataUri }}" alt="{{ $employee->full_name }}" class="w-24 h-28 object-cover rounded-lg border-2 border-white shadow">
                    @endif
                </div>
                <div class="flex-1 p-3 text-slate-900">
                    <div class="text-[8px] uppercase tracking-wider text-slate-400">Name</div>
                    <div class="text-sm font-extrabold leading-tight">{{ $employee->full_name }}</div>
                    <div class="mt-2 text-[8px] uppercase tracking-wider text-slate-400">Employee ID</div>
                    <div class="text-xs font-black">{{ $employee->employee_id }}</div>
                    <div class="mt-2 text-[8px] uppercase tracking-wider text-slate-400">Department</div>
                    <div class="text-[10px] font-bold">{{ $employee->department?->name ?? '—' }}</div>
                    <div class="mt-1 text-[8px] uppercase tracking-wider text-slate-400">Job Title</div>
                    <div class="text-[10px] font-bold">{{ $employee->position?->title ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
