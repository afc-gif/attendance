@extends('layouts.admin')
@section('title', 'HR Settings')
@section('content')
<div class="max-w-3xl space-y-6">
    <div><h1 class="text-2xl font-extrabold text-slate-900">HR Settings</h1><p class="text-xs text-slate-500">Basic human resources configuration</p></div>
    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-5">
        <div><label class="text-xs font-bold text-slate-600">Employee ID format</label><div class="mt-1 px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold text-slate-700">{{ $settings['employee_id_format'] }}</div></div>
        <form method="POST" action="{{ route('admin.hr.settings.update') }}" class="space-y-4">@csrf @method('PUT')
            <label class="block text-xs font-bold text-slate-600">Default probation period (days)<input type="number" name="probation_default_days" min="1" max="365" value="{{ old('probation_default_days', $settings['probation_default_days']) }}" required class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
            <label class="block text-xs font-bold text-slate-600">HR contact email<input type="email" name="hr_contact_email" value="{{ old('hr_contact_email', $settings['hr_contact_email']) }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
            <button class="bg-indigo-600 text-white px-4 py-2.5 rounded-xl text-xs font-bold">Save Settings</button>
        </form>
    </div>
</div>
@endsection
