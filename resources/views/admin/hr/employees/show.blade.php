@extends('layouts.admin')
@section('title', $employee->full_name)
@section('content')
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div><p class="text-xs font-bold text-indigo-600">{{ $employee->employee_id }} · {{ $employee->company }}</p><h1 class="text-2xl font-extrabold text-slate-900">{{ $employee->full_name }}</h1></div>
        <div class="flex gap-2"><a href="{{ route('admin.hr.employees.edit', $employee) }}" class="px-4 py-2.5 rounded-xl border border-slate-300 text-xs font-bold">Edit</a><form method="POST" action="{{ route('admin.hr.employees.destroy', $employee) }}" onsubmit="return confirm('Deactivate this employee record?')">@csrf @method('DELETE')<button class="px-4 py-2.5 rounded-xl bg-rose-600 text-white text-xs font-bold">Deactivate</button></form></div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                <h2 class="font-extrabold text-sm mb-4">Personal Information</h2>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs"><div><dt class="text-slate-400">Date of birth</dt><dd class="font-bold">{{ $employee->date_of_birth?->format('M j, Y') ?? '—' }}</dd></div><div><dt class="text-slate-400">Gender</dt><dd class="font-bold">{{ $employee->gender ?? '—' }}</dd></div><div><dt class="text-slate-400">Nationality</dt><dd class="font-bold">{{ $employee->nationality ?? '—' }}</dd></div><div><dt class="text-slate-400">Phone</dt><dd class="font-bold">{{ $employee->phone ?? '—' }}</dd></div><div><dt class="text-slate-400">Email</dt><dd class="font-bold">{{ $employee->email ?? '—' }}</dd></div><div><dt class="text-slate-400">Religion</dt><dd class="font-bold">{{ $employee->religion ?? '—' }}</dd></div><div class="md:col-span-2"><dt class="text-slate-400">Residential address</dt><dd class="font-bold">{{ $employee->residential_address ?? '—' }}</dd></div></dl>
            </div>
            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                <h2 class="font-extrabold text-sm mb-4">Emergency and Family</h2>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs"><div><dt class="text-slate-400">Marital status</dt><dd class="font-bold">{{ $employee->marital_status ?? '—' }}</dd></div><div><dt class="text-slate-400">Next of kin</dt><dd class="font-bold">{{ $employee->next_of_kin ?? '—' }}</dd></div><div><dt class="text-slate-400">Relationship</dt><dd class="font-bold">{{ $employee->next_of_kin_relationship ?? '—' }}</dd></div><div><dt class="text-slate-400">Next-of-kin phone</dt><dd class="font-bold">{{ $employee->next_of_kin_phone ?? '—' }}</dd></div><div class="md:col-span-2"><dt class="text-slate-400">Next-of-kin address</dt><dd class="font-bold">{{ $employee->next_of_kin_address ?? '—' }}</dd></div></dl>
            </div>
            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                <h2 class="font-extrabold text-sm mb-4">Employment History</h2>
                @forelse($employee->employmentHistories as $history)<div class="py-2 border-b border-slate-100 text-xs"><span class="font-bold">{{ ucfirst(str_replace('_',' ', $history->event_type)) }}</span><span class="text-slate-500"> · {{ $history->effective_date->format('M j, Y') }}</span></div>@empty<div class="text-xs text-slate-400">No history recorded.</div>@endforelse
            </div>
        </div>
        <div class="space-y-6">
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm text-xs space-y-3"><h2 class="font-extrabold text-sm">Employment</h2><div class="flex justify-between"><span class="text-slate-400">Department</span><strong>{{ $employee->department?->name ?? '—' }}</strong></div><div class="flex justify-between"><span class="text-slate-400">Position</span><strong>{{ $employee->position?->title ?? '—' }}</strong></div><div class="flex justify-between"><span class="text-slate-400">Manager</span><strong>{{ $employee->reportingManager?->full_name ?? '—' }}</strong></div><div class="flex justify-between"><span class="text-slate-400">Status</span><strong>{{ ucfirst($employee->employment_status) }}</strong></div><div class="flex justify-between"><span class="text-slate-400">Confirmation</span><strong>{{ ucfirst($employee->confirmation_status) }}</strong></div><div class="flex justify-between"><span class="text-slate-400">Probation end</span><strong>{{ $employee->probation_end_date?->format('M j, Y') ?? '—' }}</strong></div><div class="flex justify-between"><span class="text-slate-400">Login account</span><strong>{{ $employee->user?->email ?? 'Not activated' }}</strong></div></div>
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm"><h2 class="font-extrabold text-sm mb-3">Documents</h2>
                @forelse($employee->documents as $document)<div class="flex items-center justify-between py-2 text-xs border-b border-slate-100"><div><div class="font-bold">{{ $document->original_name }}</div><div class="text-slate-400">{{ $document->document_type }}</div></div><a href="{{ route('admin.hr.documents.download', $document) }}" class="text-indigo-600 font-bold">Download</a></div>@empty<p class="text-xs text-slate-400">No documents uploaded.</p>@endforelse
                <form method="POST" action="{{ route('admin.hr.documents.store', $employee) }}" enctype="multipart/form-data" class="mt-4 space-y-2">@csrf<input name="document_type" required placeholder="Document type" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs"><input type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png" class="w-full text-xs"><button class="bg-slate-900 text-white rounded-lg px-3 py-2 text-xs font-bold">Upload Securely</button></form>
            </div>
        </div>
    </div>
</div>
@endsection
