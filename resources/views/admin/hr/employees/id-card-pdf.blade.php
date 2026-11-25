<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; size: 85.6mm 54mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #0f172a; }
        .card-page { position: relative; width: 85.6mm; height: 54mm; overflow: hidden; background: #fff; }
        .front { page-break-after: always; }
        .front-top { position: absolute; top: 0; left: 0; width: 77.6mm; height: 7.8mm; padding: 2mm 4mm; color: #fff; background: #0f172a; border-bottom: 1.2mm solid #35B3FF; }
        .afc .front-top { border-bottom-color: #A37231; }
        .logo { position: absolute; top: 1.5mm; left: 4mm; width: 10mm; height: 9mm; object-fit: contain; }
        .brand { position: absolute; top: 2.7mm; left: 17mm; font-size: 9pt; font-weight: bold; letter-spacing: 1px; }
        .kicker { margin-top: 1mm; color: #cbd5e1; font-size: 5pt; font-weight: bold; letter-spacing: 1.2px; }
        .photo-area { position: absolute; top: 13mm; left: 0; width: 23mm; height: 30mm; padding: 2mm; text-align: center; background: #35B3FF; }
        .afc .photo-area { background: #A37231; }
        .photo { position: absolute; top: 2.5mm; left: 2.5mm; width: 22mm; height: 29mm; object-fit: cover; border: .8mm solid #fff; border-radius: 1.5mm; }
        .details { position: absolute; top: 13mm; left: 27mm; width: 50.6mm; height: 28mm; padding: 3mm 4mm; }
        .label { margin-top: 1.3mm; color: #64748b; font-size: 5pt; font-weight: bold; letter-spacing: .7px; text-transform: uppercase; }
        .name { margin-top: .5mm; font-size: 10pt; font-weight: bold; line-height: 1.15; }
        .role { margin-top: .6mm; color: #475569; font-size: 6pt; font-weight: bold; }
        .employee-id { display: inline-block; margin-top: .6mm; padding: 1mm 2mm; color: #fff; background: #35B3FF; border-radius: 1mm; font-size: 7pt; font-weight: bold; letter-spacing: .4px; }
        .afc .employee-id { background: #A37231; }
        .department { margin-top: 1.2mm; font-size: 6pt; font-weight: bold; }
        .front-footer { position: absolute; bottom: 0; left: 0; width: 77.6mm; height: 3mm; padding: 2mm 4mm; background: #f1f5f9; color: #475569; font-size: 4.5pt; font-weight: bold; }
        .front-footer-left { position: absolute; top: 2mm; left: 4mm; }
        .front-footer-right { position: absolute; top: 2mm; right: 4mm; }
        .back-top { position: absolute; top: 0; left: 0; width: 77.6mm; height: 7.8mm; padding: 2mm 4mm; color: #fff; background: #0f172a; border-bottom: 1.2mm solid #35B3FF; }
        .afc .back-top { border-bottom-color: #A37231; }
        .back-logo { position: absolute; top: 1.5mm; left: 4mm; width: 9mm; height: 9mm; object-fit: contain; }
        .back-brand { position: absolute; top: 2.5mm; left: 16mm; font-size: 8pt; font-weight: bold; letter-spacing: 1px; }
        .back-subtitle { margin-top: 1mm; color: #cbd5e1; font-size: 5pt; font-weight: bold; letter-spacing: .8px; }
        .back-content { position: absolute; top: 14.5mm; left: 4mm; width: 77.6mm; height: 32mm; }
        .contact-box { position: absolute; top: 0; left: 0; width: 72.4mm; height: 10.4mm; padding: 1.3mm 2mm; background: #f1f5f9; border-left: 1.2mm solid #35B3FF; }
        .afc .contact-box { border-left-color: #A37231; }
        .contact-title { color: #35B3FF; font-size: 6pt; font-weight: bold; letter-spacing: .8px; }
        .afc .contact-title { color: #A37231; }
        .contact-name { margin-top: .7mm; font-size: 8pt; font-weight: bold; }
        .contact-phone { margin-top: .4mm; color: #475569; font-size: 6pt; }
        .notice-title { position: absolute; top: 15.5mm; left: 0; color: #0f172a; font-size: 6pt; font-weight: bold; letter-spacing: .8px; }
        .notice-list { position: absolute; top: 19.5mm; left: 3mm; width: 73mm; margin: 0; padding: 0; color: #475569; font-size: 5.3pt; line-height: 1.55; }
        .return-strip { position: absolute; bottom: 0; left: 0; width: 81.6mm; height: 2mm; padding: 2mm; color: #fff; background: #0f172a; border-top: 1mm solid #35B3FF; text-align: center; font-size: 6pt; font-weight: bold; }
        .afc .return-strip { border-top-color: #A37231; }
    </style>
</head>
<body>
    <div class="card-page front {{ $branding['css_class'] }}">
        <div class="front-top">
            @if($branding['logo'])
                <img class="logo" src="{{ $branding['logo'] }}" alt="{{ $branding['name'] }} logo">
            @endif
            <div class="brand">{{ $branding['name'] }}<div class="kicker">Staff identification</div></div>
        </div>
        <div>
            <div class="photo-area">
                @if($photoDataUri)
                    <img class="photo" src="{{ $photoDataUri }}" alt="{{ $employee->full_name }}">
                @endif
            </div>
            <div class="details">
                <div class="label">Full name</div>
                <div class="name">{{ $employee->full_name }}</div>
                <div class="role">{{ $employee->position?->title ?? 'Staff member' }}</div>
                <div class="label">Employee ID</div>
                <div class="employee-id">{{ $employee->employee_id }}</div>
                <div class="department">{{ $employee->department?->name ?? '—' }} · Department</div>
            </div>
        </div>
        <div class="front-footer">
            <span class="front-footer-left">{{ $employee->employment_date?->format('M Y') ? 'Since '.$employee->employment_date->format('M Y') : 'Authorized staff' }}</span>
            <span class="front-footer-right">{{ $branding['name'] }} · STAFF</span>
        </div>
    </div>
    <div class="card-page back {{ $branding['css_class'] }}">
        <div class="back-top">
            @if($branding['logo'])
                <img class="back-logo" src="{{ $branding['logo'] }}" alt="{{ $branding['name'] }} logo">
            @endif
            <div class="back-brand">{{ $branding['name'] }}<div class="back-subtitle">EMPLOYEE IDENTIFICATION CARD</div></div>
        </div>
        <div class="back-content">
            <div class="contact-box">
                <div class="contact-title">EMERGENCY CONTACT</div>
                <div class="contact-name">{{ $emergencyContactName ?: 'Not provided' }}</div>
                <div class="contact-phone">{{ $emergencyContactPhone ?: 'Contact HR for assistance' }}</div>
            </div>
            <div class="notice-title">IMPORTANT INFORMATION</div>
            <ul class="notice-list">
                <li>This card remains the property of {{ $branding['name'] }}.</li>
                <li>Display it while on duty and do not transfer it to another person.</li>
                <li>Report loss or damage to HR promptly.</li>
            </ul>
        </div>
        <div class="return-strip">If found, please return this card to {{ $branding['name'] }} HR.</div>
    </div>
</body>
</html>
