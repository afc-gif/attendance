<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; size: 54mm 85.6mm; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #0f172a; }
        .card-page { position: relative; width: 54mm; height: 85.6mm; overflow: hidden; background: #fff; }
        .front { page-break-after: always; }
        .brand-band { position: absolute; top: 0; left: 0; width: 54mm; height: 15mm; background: #0f172a; }
        .logo { position: absolute; top: 2mm; left: 2.5mm; width: 10mm; height: 10mm; object-fit: contain; }
        .company-name { position: absolute; top: 2.1mm; left: 14mm; color: #fff; font-size: 7pt; font-weight: bold; }
        .tagline { position: absolute; top: 7mm; left: 14mm; color: #dbeafe; font-size: 4.1pt; font-weight: bold; letter-spacing: .3px; }
        .card-title { position: absolute; top: 15mm; left: 0; width: 54mm; height: 6mm; padding-top: 1.4mm; color: #fff; background: #35B3FF; text-align: center; font-size: 6pt; font-weight: bold; letter-spacing: 1px; }
        .afc .card-title { background: #A37231; }
        .photo-frame { position: absolute; top: 24mm; left: 3mm; width: 18mm; height: 28mm; background: #35B3FF; }
        .afc .photo-frame { background: #A37231; }
        .photo { position: absolute; top: 1mm; left: 1mm; width: 16mm; height: 26mm; object-fit: cover; border: .6mm solid #fff; }
        .details { position: absolute; top: 23mm; left: 23mm; width: 28mm; }
        .field { margin-bottom: .55mm; }
        .field-label { color: #64748b; font-size: 3.8pt; font-weight: bold; letter-spacing: .5px; text-transform: uppercase; }
        .field-value { margin-top: .25mm; color: #0f172a; font-size: 4.8pt; font-weight: bold; line-height: 1.05; }
        .employee-id { display: inline-block; padding: .8mm 1.4mm; color: #fff; background: #35B3FF; font-size: 5.5pt; letter-spacing: .3px; }
        .afc .employee-id { background: #A37231; }
        .qr-front { position: absolute; top: 63.5mm; left: 3mm; width: 13mm; height: 13mm; }
        .qr-image { width: 13mm; height: 13mm; }
        .qr-caption { position: absolute; top: 77mm; left: 1.5mm; width: 16mm; color: #475569; text-align: center; font-size: 3.2pt; font-weight: bold; }
        .signatures { position: absolute; top: 68mm; left: 19mm; width: 33mm; height: 9mm; }
        .signature { position: absolute; top: 0; width: 15mm; height: 8mm; text-align: center; }
        .signature.staff { left: 0; }
        .signature.hr { right: 0; }
        .signature img { width: 14mm; height: 4.5mm; object-fit: contain; }
        .signature-line { border-top: .2mm solid #334155; margin-top: .3mm; }
        .signature-label { margin-top: .4mm; color: #475569; font-size: 3.4pt; font-weight: bold; }
        .front-footer { position: absolute; top: 80mm; left: 0; width: 54mm; height: 5.6mm; box-sizing: border-box; padding-top: 1.2mm; color: #fff; background: #0f172a; border-top: 1mm solid #35B3FF; text-align: center; font-size: 4.2pt; font-weight: bold; letter-spacing: .3px; }
        .afc .front-footer { border-top-color: #A37231; }
        .back-header { position: absolute; top: 0; left: 0; width: 54mm; height: 21mm; color: #fff; background: #0f172a; border-bottom: 1mm solid #35B3FF; }
        .afc .back-header { border-bottom-color: #A37231; }
        .back-logo { position: absolute; top: 2mm; left: 2.5mm; width: 8mm; height: 8mm; object-fit: contain; }
        .back-company { position: absolute; top: 2.6mm; left: 12mm; width: 25mm; color: #fff; font-size: 6pt; font-weight: bold; }
        .back-location { position: absolute; top: 10mm; left: 3mm; width: 33mm; color: #e2e8f0; font-size: 4.2pt; line-height: 1.25; }
        .back-phone { position: absolute; top: 15.4mm; left: 3mm; color: #fff; font-size: 4.3pt; font-weight: bold; }
        .back-website { position: absolute; top: 18mm; left: 3mm; color: #e2e8f0; font-size: 4pt; }
        .qr-back { position: absolute; top: 4mm; right: 2.5mm; width: 12mm; height: 12mm; background: #fff; }
        .emergency { position: absolute; top: 23mm; left: 3mm; width: 48mm; height: 13mm; padding: 1.5mm 2mm; background: #f1f5f9; border-left: 1mm solid #35B3FF; }
        .afc .emergency { border-left-color: #A37231; }
        .emergency-title { color: #35B3FF; font-size: 5pt; font-weight: bold; letter-spacing: .7px; }
        .afc .emergency-title { color: #A37231; }
        .emergency-value { margin-top: .8mm; color: #0f172a; font-size: 6pt; font-weight: bold; }
        .emergency-phone { margin-top: .4mm; color: #475569; font-size: 5pt; }
        .notice-title { position: absolute; top: 39mm; left: 3mm; color: #0f172a; font-size: 5.2pt; font-weight: bold; letter-spacing: .6px; }
        .notice-list { position: absolute; top: 43mm; left: 3mm; width: 48mm; margin: 0; padding-left: 3mm; color: #334155; font-size: 4.15pt; line-height: 1.38; }
        .notice-list li { margin-bottom: .8mm; }
        .return-strip { position: absolute; top: 70mm; left: 0; width: 54mm; height: 6mm; box-sizing: border-box; padding-top: 1.2mm; color: #fff; background: #0f172a; border-top: 1mm solid #35B3FF; text-align: center; font-size: 4.5pt; font-weight: bold; }
        .afc .return-strip { border-top-color: #A37231; }
        .back-tagline { position: absolute; top: 78mm; left: 2mm; width: 50mm; color: #64748b; text-align: center; font-size: 4pt; font-weight: bold; letter-spacing: .5px; }
    </style>
</head>
<body>
    <div class="card-page front {{ $branding['css_class'] }}">
        <div class="brand-band">
            @if($branding['logo'])
                <img class="logo" src="{{ $branding['logo'] }}" alt="{{ $branding['name'] }} logo">
            @endif
            <div class="company-name">{{ $branding['official_name'] }}</div>
            <div class="tagline">{{ $branding['tagline'] }}</div>
        </div>
        <div class="card-title">STAFF IDENTIFICATION CARD</div>
        <div class="photo-frame">
            <img class="photo" src="{{ $photoDataUri }}" alt="{{ $employee->full_name }}">
        </div>
        <div class="details">
            <div class="field">
                <div class="field-label">Full name</div>
                <div class="field-value">{{ $employee->full_name }}</div>
            </div>
            <div class="field">
                <div class="field-label">Staff ID</div>
                <div class="field-value"><span class="employee-id">{{ $employee->employee_id }}</span></div>
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
        <div class="qr-front"><img class="qr-image" src="{{ $qrDataUri }}" alt="Scan to visit {{ $branding['website'] }}"></div>
        <div class="qr-caption">Scan for {{ $branding['name'] }} website</div>
        <div class="signatures">
            <div class="signature staff">
                @if($employeeSignatureDataUri)
                    <img src="{{ $employeeSignatureDataUri }}" alt="Employee signature">
                @endif
                <div class="signature-line"></div>
                <div class="signature-label">STAFF SIGNATURE</div>
            </div>
            <div class="signature hr">
                @if($hrSignatureDataUri)
                    <img src="{{ $hrSignatureDataUri }}" alt="HR signature">
                @endif
                <div class="signature-line"></div>
                <div class="signature-label">HR SIGNATURE</div>
            </div>
        </div>
        <div class="front-footer">{{ $branding['tagline'] }}</div>
    </div>

    <div class="card-page back {{ $branding['css_class'] }}">
        <div class="back-header">
            @if($branding['logo'])
                <img class="back-logo" src="{{ $branding['logo'] }}" alt="{{ $branding['name'] }} logo">
            @endif
            <div class="back-company">{{ $branding['official_name'] }}</div>
            <div class="back-location">{{ $branding['location'] }}</div>
            <div class="back-phone">☎ {{ $branding['phone'] }}</div>
            <div class="back-website">{{ $branding['website'] }}</div>
            <img class="qr-back" src="{{ $qrDataUri }}" alt="Company website QR code">
        </div>
        <div class="emergency">
            <div class="emergency-title">EMERGENCY CONTACT</div>
            <div class="emergency-value">{{ $emergencyContactName ?: 'Not provided' }}</div>
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
</body>
</html>
