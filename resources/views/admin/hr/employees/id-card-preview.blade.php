@extends('layouts.admin')
@section('title', 'ID Card Preview - '.$employee->employee_id)
@push('styles')
<style>
.id-card {
    width: 272px;
    height: 431px;
    position: relative;
    overflow: hidden;
    box-sizing: border-box;
    border: 1px solid #d5deea;
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 24px 60px rgb(15 23 42 / 20%);
    color: #0f172a;
}
.brand-band {
    height: 76px;
    position: absolute;
    inset: 0 0 auto;
    overflow: hidden;
    background: #0f172a;
}
.brand-band::after {
    content: "";
    position: absolute;
    right: -24px;
    top: -38px;
    width: 92px;
    height: 92px;
    border: 10px solid var(--card-primary);
    border-radius: 50%;
    opacity: .8;
}
.brand-logo { position: absolute; z-index: 1; left: 13px; top: 12px; width: 50px; height: 50px; object-fit: contain; }
.brand-name { position: absolute; z-index: 1; left: 72px; top: 16px; max-width: 185px; color: #fff; font-size: 15px; line-height: 1.15; font-weight: 900; }
.brand-tagline { position: absolute; z-index: 1; left: 72px; top: 39px; color: #dbeafe; font-size: 7px; letter-spacing: .04em; font-weight: 800; }
.card-title {
    position: absolute;
    top: 76px;
    left: 0;
    width: 100%;
    height: 31px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    background: var(--card-primary);
    font-size: 10px;
    letter-spacing: .14em;
    font-weight: 900;
}
.photo-frame { position: absolute; left: 14px; top: 121px; width: 91px; height: 141px; padding: 5px; background: var(--card-primary); border-radius: 14px; }
.employee-photo { width: 100%; height: 100%; object-fit: cover; border: 2px solid #fff; border-radius: 10px; }
.details { position: absolute; top: 116px; left: 116px; width: 141px; }
.field { margin-bottom: 4px; }
.field-label { color: #64748b; font-size: 6px; line-height: 1.1; letter-spacing: .11em; text-transform: uppercase; font-weight: 900; }
.field-value { margin-top: 2px; color: #0f172a; font-size: 9px; line-height: 1.1; font-weight: 800; overflow-wrap: anywhere; }
.name-value { font-size: 11px; line-height: 1.1; }
.staff-id { display: inline-block; padding: 4px 7px; color: #fff; background: var(--card-primary); border-radius: 6px; font-size: 10px; letter-spacing: .04em; }
.front-qr { position: absolute; left: 15px; top: 320px; width: 66px; height: 66px; padding: 3px; background: white; border: 1px solid #e2e8f0; border-radius: 7px; }
.front-qr img, .back-qr img { width: 100%; height: 100%; }
.qr-caption { position: absolute; left: 8px; top: 389px; width: 80px; text-align: center; color: #64748b; font-size: 6px; font-weight: 800; }
.signature-area { position: absolute; top: 344px; left: 96px; right: 12px; height: 42px; display: flex; gap: 7px; }
.signature-box { width: 50%; min-width: 0; text-align: center; }
.signature-image { display: block; width: 100%; height: 24px; object-fit: contain; border-bottom: 1px solid #334155; }
.signature-label { margin-top: 3px; color: #475569; font-size: 5px; font-weight: 900; letter-spacing: .03em; }
.front-footer {
    position: absolute;
    top: 405px;
    left: 0;
    width: 100%;
    height: 26px;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 12px;
    box-sizing: border-box;
    color: #fff;
    background: #0f172a;
    border-top: 4px solid var(--card-primary);
    font-size: 7px;
    font-weight: 900;
    letter-spacing: .06em;
    text-align: center;
}
.back-header {
    position: absolute;
    inset: 0 0 auto;
    height: 106px;
    color: #fff;
    background: #0f172a;
    border-bottom: 5px solid var(--card-primary);
}
.back-logo { position: absolute; left: 13px; top: 12px; width: 40px; height: 40px; object-fit: contain; }
.back-company { position: absolute; left: 60px; top: 16px; width: 145px; color: #fff; font-size: 11px; font-weight: 900; }
.back-location { position: absolute; left: 14px; top: 58px; width: 186px; color: #e2e8f0; font-size: 7px; line-height: 1.25; }
.back-phone { position: absolute; left: 14px; top: 80px; color: #fff; font-size: 8px; font-weight: 800; }
.back-website { position: absolute; left: 14px; top: 94px; color: #cbd5e1; font-size: 7px; }
.back-qr { position: absolute; right: 12px; top: 20px; width: 60px; height: 60px; padding: 3px; background: #fff; border-radius: 5px; }
.emergency-panel { position: absolute; top: 116px; left: 14px; right: 14px; height: 66px; box-sizing: border-box; padding: 9px 11px; background: #f1f5f9; border-left: 4px solid var(--card-primary); border-radius: 0 10px 10px 0; }
.emergency-title { color: var(--card-primary); font-size: 8px; font-weight: 900; letter-spacing: .11em; }
.emergency-name { margin-top: 6px; color: #0f172a; font-size: 11px; font-weight: 900; }
.emergency-phone { margin-top: 3px; color: #475569; font-size: 9px; font-weight: 700; }
.notice-title { position: absolute; top: 197px; left: 14px; color: #0f172a; font-size: 9px; font-weight: 900; letter-spacing: .1em; }
.notice-list { position: absolute; top: 218px; left: 13px; right: 12px; margin: 0; padding-left: 16px; color: #334155; font-size: 8px; line-height: 1.38; }
.notice-list li { padding-left: 1px; margin-bottom: 7px; }
.notice-list li::marker { color: var(--card-primary); }
.return-strip {
    position: absolute;
    top: 353px;
    left: 0;
    width: 100%;
    min-height: 33px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 12px;
    box-sizing: border-box;
    color: #fff;
    background: #0f172a;
    border-top: 4px solid var(--card-primary);
    text-align: center;
    font-size: 8px;
    font-weight: 900;
}
.back-tagline { position: absolute; top: 397px; left: 8px; right: 8px; color: #64748b; text-align: center; font-size: 7px; font-weight: 900; letter-spacing: .06em; }
@media (max-width: 760px) {
    .card-pair { flex-direction: column; align-items: center; }
}
@media print {
    body * { visibility: hidden; }
    #card-print-area, #card-print-area * { visibility: visible; }
    @page { size: 54mm 85.6mm; margin: 0; }
    #card-print-area { position: absolute; left: 0; top: 0; display: block; }
    #card-print-area > section { position: relative; width: 54mm; height: 85.6mm; margin: 0 !important; page-break-after: always; }
    #card-print-area > section:last-child { page-break-after: auto; }
    #card-print-area h2 { display: none; }
    .id-card { position: absolute; top: 0; left: 0; width: 272px; height: 431px; transform: scale(.75); transform-origin: top left; border-radius: 3mm; box-shadow: none; }
}
</style>
@endpush
@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-bold {{ $branding['css_class'] }}-text">{{ $employee->employee_id }}</p>
            <h1 class="text-2xl font-extrabold text-slate-900">Employee ID Card Preview</h1>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.hr.employees.show', $employee) }}" class="px-4 py-2.5 rounded-xl border border-slate-300 text-xs font-bold">Back to Employee</a>
            <a href="{{ route('admin.hr.employees.id-card.download', $employee) }}" class="px-4 py-2.5 rounded-xl text-white text-xs font-bold {{ $branding['css_class'] }}-button">Download PDF</a>
        </div>
    </div>
    <p class="text-sm text-slate-600">Portrait card, front and back. The two-page PDF is sized for a standard 54 × 85.6 mm ID card; print double-sided at 100% scale.</p>
    <div id="card-print-area" class="card-pair flex flex-wrap items-start justify-center gap-8">
        <section class="space-y-2">
            <h2 class="text-xs font-extrabold uppercase tracking-widest text-slate-500">Front</h2>
            <div class="id-card {{ $branding['css_class'] }}" style="--card-primary: {{ $branding['primary'] }}">
                <div class="brand-band">
                    @if($branding['logo'])
                        <img src="{{ $branding['logo'] }}" alt="{{ $branding['official_name'] }} logo" class="brand-logo">
                    @endif
                    <div class="brand-name">{{ $branding['official_name'] }}</div>
                    <div class="brand-tagline">{{ $branding['tagline'] }}</div>
                </div>
                <div class="card-title">STAFF IDENTIFICATION CARD</div>
                <div class="photo-frame">
                    <img src="{{ $photoDataUri }}" alt="{{ $employee->full_name }}" class="employee-photo">
                </div>
                <div class="details">
                    <div class="field">
                        <div class="field-label">Full name</div>
                        <div class="field-value name-value">{{ $employee->full_name }}</div>
                    </div>
                    <div class="field">
                        <div class="field-label">Staff ID</div>
                        <div class="field-value"><span class="staff-id">{{ $employee->employee_id }}</span></div>
                    </div>
                    <div class="field">
                        <div class="field-label">Job title</div>
                        <div class="field-value">{{ $employee->position?->title ?? '—' }}</div>
                    </div>
                    <div class="field">
                        <div class="field-label">Department</div>
                        <div class="field-value">{{ $employee->department?->name ?? '—' }}</div>
                    </div>
                    <div class="field">
                        <div class="field-label">Location</div>
                        <div class="field-value">{{ $branding['location'] }}</div>
                    </div>
                    <div class="field">
                        <div class="field-label">Date of employment</div>
                        <div class="field-value">{{ $employee->employment_date?->format('d M Y') ?? '—' }}</div>
                    </div>
                </div>
                <div class="front-qr"><img src="{{ $qrDataUri }}" alt="Scan to visit {{ $branding['website'] }}"></div>
                <div class="qr-caption">Scan for {{ $branding['name'] }} website</div>
                <div class="signature-area">
                    <div class="signature-box">
                        <img src="{{ $employeeSignatureDataUri }}" alt="Employee signature" class="signature-image">
                        <div class="signature-label">STAFF SIGNATURE</div>
                    </div>
                    <div class="signature-box">
                        <img src="{{ $hrSignatureDataUri }}" alt="HR signature" class="signature-image">
                        <div class="signature-label">HR SIGNATURE</div>
                    </div>
                </div>
                <div class="front-footer">{{ $branding['tagline'] }}</div>
            </div>
        </section>
        <section class="space-y-2">
            <h2 class="text-xs font-extrabold uppercase tracking-widest text-slate-500">Back</h2>
            <div class="id-card card-back {{ $branding['css_class'] }}" style="--card-primary: {{ $branding['primary'] }}">
                <div class="back-header">
                    @if($branding['logo'])
                        <img src="{{ $branding['logo'] }}" alt="{{ $branding['official_name'] }} logo" class="back-logo">
                    @endif
                    <div class="back-company">{{ $branding['official_name'] }}</div>
                    <div class="back-location">{{ $branding['location'] }}</div>
                    <div class="back-phone">☎ {{ $branding['phone'] }}</div>
                    <div class="back-website">{{ $branding['website'] }}</div>
                    <div class="back-qr"><img src="{{ $qrDataUri }}" alt="Company website QR code"></div>
                </div>
                <div class="emergency-panel">
                    <div class="emergency-title">EMERGENCY CONTACT</div>
                    <div class="emergency-name">{{ $emergencyContactName ?: 'Not provided' }}</div>
                    <div class="emergency-phone">{{ $emergencyContactPhone ?: 'Contact HR for assistance' }}</div>
                </div>
                <div class="notice-title">IMPORTANT NOTICE</div>
                <ul class="notice-list">
                    <li>This ID card is the property of {{ $branding['official_name'] }} and remains valid while employed.</li>
                    <li>It must be worn at all times while on duty.</li>
                    <li>Do not share this ID card with anyone.</li>
                    <li>Report loss or damage to HR immediately.</li>
                    <li>Unauthorized use breaches company policy and may attract disciplinary action.</li>
                </ul>
                <div class="return-strip">Return this ID card to HR if found.</div>
                <div class="back-tagline">{{ $branding['tagline'] }}</div>
            </div>
        </section>
    </div>
</div>
@endsection
