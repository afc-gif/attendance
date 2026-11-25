@extends('layouts.admin')
@section('title', 'ID Card Preview - '.$employee->employee_id)
@push('styles')
<style>
.artsci-text { color: #35B3FF; }
.afc-text { color: #A37231; }
.artsci-button { background: #35B3FF; }
.afc-button { background: #A37231; }
.id-card {
    width: 430px;
    height: 272px;
    box-sizing: border-box;
    border-radius: 20px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 22px 55px rgb(15 23 42 / 18%);
    border: 1px solid #dbe3ee;
    position: relative;
}
.card-top {
    height: 60px;
    padding: 10px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: #fff;
    background: #0f172a;
    border-bottom: 5px solid var(--card-primary);
}
.card-logo { width: 46px; height: 46px; object-fit: contain; }
.card-company { font-size: 15px; font-weight: 900; letter-spacing: .08em; }
.card-kicker { font-size: 8px; font-weight: 800; letter-spacing: .2em; color: #cbd5e1; text-transform: uppercase; }
.card-main { display: flex; height: 164px; }
.card-photo-wrap {
    width: 130px;
    flex: none;
    padding: 13px;
    background: linear-gradient(145deg, var(--card-primary), color-mix(in srgb, var(--card-primary), #0f172a 20%));
}
.card-photo { width: 104px; height: 138px; object-fit: cover; border: 3px solid #fff; border-radius: 12px; }
.card-details { padding: 14px 16px; flex: 1; min-width: 0; color: #0f172a; }
.card-name { font-size: 16px; line-height: 1.15; font-weight: 900; }
.card-role { margin-top: 5px; font-size: 10px; font-weight: 700; color: #475569; }
.card-id-label { margin-top: 12px; color: #64748b; font-size: 8px; font-weight: 800; letter-spacing: .15em; text-transform: uppercase; }
.card-id { display: inline-block; margin-top: 3px; padding: 5px 9px; color: #fff; background: var(--card-primary); border-radius: 7px; font-size: 11px; font-weight: 900; letter-spacing: .06em; }
.card-department { margin-top: 10px; font-size: 9px; font-weight: 800; }
.card-footer {
    height: 41px;
    padding: 0 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f1f5f9;
    border-top: 1px solid #e2e8f0;
    color: #475569;
    font-size: 8px;
    font-weight: 800;
}
.card-back { padding: 17px 19px; color: #0f172a; }
.card-back-top { display: flex; align-items: center; gap: 10px; padding-bottom: 10px; border-bottom: 4px solid var(--card-primary); }
.card-back-logo { width: 34px; height: 34px; object-fit: contain; }
.card-back-heading { font-size: 12px; font-weight: 900; letter-spacing: .12em; }
.card-back-subtitle { margin-top: 2px; color: #64748b; font-size: 8px; font-weight: 700; }
.emergency-panel { margin-top: 12px; padding: 9px 11px; background: #f1f5f9; border-radius: 10px; border-left: 4px solid var(--card-primary); }
.emergency-title { color: var(--card-primary); font-size: 8px; font-weight: 900; letter-spacing: .14em; }
.emergency-value { margin-top: 3px; font-size: 11px; font-weight: 800; }
.notice-title { margin-top: 11px; font-size: 8px; font-weight: 900; letter-spacing: .14em; }
.notice-list { margin-top: 5px; padding-left: 15px; color: #475569; font-size: 8px; line-height: 1.55; }
.return-strip { position: absolute; left: 0; right: 0; bottom: 0; padding: 9px 14px; color: #fff; background: #0f172a; border-top: 4px solid var(--card-primary); text-align: center; font-size: 9px; font-weight: 800; letter-spacing: .04em; }
@media (max-width: 900px) {
    .card-pair { flex-direction: column; align-items: center; }
}
@media print {
    body * { visibility: hidden; }
    #card-print-area, #card-print-area * { visibility: visible; }
    #card-print-area { position: absolute; left: 0; top: 0; display: flex; gap: 8mm; }
    .id-card { width: 85.6mm; height: 54mm; border-radius: 3mm; box-shadow: none; }
}
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
    <p class="text-sm text-slate-600">Both sides use {{ $branding['name'] }} branding. The downloaded PDF contains one card-sized page per side: front first, back second. Print double-sided at 100% scale.</p>
    <div id="card-print-area" class="card-pair flex flex-wrap justify-center gap-8">
        <section class="space-y-2">
            <h2 class="text-xs font-extrabold uppercase tracking-widest text-slate-500">Front</h2>
            <div class="id-card" style="--card-primary: {{ $branding['primary'] }}">
                <div class="card-top">
                    @if($branding['logo'])
                        <img src="{{ $branding['logo'] }}" alt="{{ $branding['name'] }} logo" class="card-logo">
                    @endif
                    <div><div class="card-company">{{ $branding['name'] }}</div><div class="card-kicker">Staff identification</div></div>
                    <div class="ml-auto rounded-full border border-white/30 px-2.5 py-1 text-[8px] font-black tracking-widest">OFFICIAL</div>
                </div>
                <div class="card-main">
                    <div class="card-photo-wrap">
                        @if($photoDataUri)
                            <img src="{{ $photoDataUri }}" alt="{{ $employee->full_name }}" class="card-photo">
                        @endif
                    </div>
                    <div class="card-details">
                        <div class="card-kicker !text-slate-400 !tracking-[.14em]">Full name</div>
                        <div class="card-name">{{ $employee->full_name }}</div>
                        <div class="card-role">{{ $employee->position?->title ?? 'Staff member' }}</div>
                        <div class="card-id-label">Employee ID</div>
                        <div class="card-id">{{ $employee->employee_id }}</div>
                        <div class="card-department">{{ $employee->department?->name ?? '—' }}<span class="font-medium text-slate-400"> · Department</span></div>
                    </div>
                </div>
                <div class="card-footer">
                    <span>{{ $employee->employment_date?->format('M Y') ? 'Since '.$employee->employment_date->format('M Y') : 'Authorized staff' }}</span>
                    <span>{{ $branding['name'] }} · STAFF ID</span>
                </div>
            </div>
        </section>
        <section class="space-y-2">
            <h2 class="text-xs font-extrabold uppercase tracking-widest text-slate-500">Back</h2>
            <div class="id-card card-back" style="--card-primary: {{ $branding['primary'] }}">
                <div class="card-back-top">
                    @if($branding['logo'])
                        <img src="{{ $branding['logo'] }}" alt="{{ $branding['name'] }} logo" class="card-back-logo">
                    @endif
                    <div><div class="card-back-heading">{{ $branding['name'] }}</div><div class="card-back-subtitle">EMPLOYEE IDENTIFICATION CARD</div></div>
                </div>
                <div class="emergency-panel">
                    <div class="emergency-title">EMERGENCY CONTACT</div>
                    <div class="emergency-value">{{ $emergencyContactName ?: 'Not provided' }}</div>
                    <div class="text-[9px] font-semibold text-slate-600">{{ $emergencyContactPhone ?: 'Contact HR for assistance' }}</div>
                </div>
                <div class="notice-title">IMPORTANT INFORMATION</div>
                <ul class="notice-list">
                    <li>This card remains the property of {{ $branding['name'] }}.</li>
                    <li>Display it while on duty and do not transfer it to another person.</li>
                    <li>Report loss or damage to HR promptly.</li>
                </ul>
                <div class="return-strip">If found, please return this card to {{ $branding['name'] }} HR.</div>
            </div>
        </section>
    </div>
</div>
@endsection
