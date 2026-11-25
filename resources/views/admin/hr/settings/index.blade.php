@extends('layouts.admin')
@section('title', 'HR Settings')
@section('content')
<div class="max-w-5xl space-y-6">
    <div><h1 class="text-2xl font-extrabold text-slate-900">HR Settings</h1><p class="text-xs text-slate-500">Basic human resources configuration</p></div>
    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-5">
        <div><label class="text-xs font-bold text-slate-600">Employee ID format</label><div class="mt-1 px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold text-slate-700">{{ $settings['employee_id_format'] }}</div></div>
        <form method="POST" action="{{ route('admin.hr.settings.update') }}" class="space-y-4">@csrf @method('PUT')
            <label class="block text-xs font-bold text-slate-600">Default probation period (days)<input type="number" name="probation_default_days" min="1" max="365" value="{{ old('probation_default_days', $settings['probation_default_days']) }}" required class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
            <label class="block text-xs font-bold text-slate-600">HR contact email<input type="email" name="hr_contact_email" value="{{ old('hr_contact_email', $settings['hr_contact_email']) }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg"></label>
            <button class="bg-indigo-600 text-white px-4 py-2.5 rounded-xl text-xs font-bold">Save Settings</button>
        </form>
    </div>
    <section class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-4">
        <div>
            <h2 class="font-extrabold text-sm text-slate-900">ID Card HR Signatures</h2>
            <p class="mt-1 text-xs text-slate-500">Each company signature is stored privately and appears on the front of that company’s employee cards.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            @foreach(['ARTSCI', 'AFC'] as $company)
                <div class="rounded-xl border border-slate-200 p-4 space-y-3">
                    <h3 class="font-extrabold text-xs">{{ $company }} HR signature</h3>
                    @if($hrSignatures[$company])
                        <img src="{{ $hrSignatures[$company] }}" alt="{{ $company }} HR signature" class="h-14 max-w-full object-contain border-b border-slate-200 pb-2">
                        <p class="text-[11px] text-emerald-700 font-bold">Signature on file</p>
                    @else
                        <p class="text-[11px] text-amber-700 font-bold">No signature uploaded yet</p>
                    @endif
                    <form method="POST" action="{{ route('admin.hr.settings.id-card-signature.upload', $company) }}" enctype="multipart/form-data" class="space-y-2">
                        @csrf
                        <label class="block text-[11px] font-bold text-slate-600">Upload a transparent PNG or JPG
                            <input type="file" name="signature" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required class="mt-1 block w-full text-xs">
                        </label>
                        <button class="w-full bg-slate-900 text-white rounded-lg px-3 py-2 text-xs font-bold">{{ $hrSignatures[$company] ? 'Replace HR Signature' : 'Save HR Signature' }}</button>
                    </form>
                    @if($hrSignatures[$company])
                        <form method="POST" action="{{ route('admin.hr.settings.id-card-signature.destroy', $company) }}" onsubmit="return confirm('Remove the {{ $company }} HR signature?')">
                            @csrf
                            @method('DELETE')
                            <button class="w-full bg-rose-50 border border-rose-200 text-rose-700 rounded-lg px-3 py-2 text-xs font-bold">Remove HR Signature</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection
