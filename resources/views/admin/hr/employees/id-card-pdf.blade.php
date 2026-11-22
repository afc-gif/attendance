<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; size: 85.6mm 54mm; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; width: 85.6mm; height: 54mm; }
        .card { width: 85.6mm; height: 54mm; background: #fff; overflow: hidden; }
        .header { height: 16mm; color: #fff; position: relative; }
        .artsci-header { background: #0F172A; }
        .afc-header { background: #111827; }
        .logo { position: absolute; left: 4mm; top: 2mm; width: 14mm; height: 12mm; object-fit: contain; }
        .company { position: absolute; right: 4mm; top: 3mm; text-align: right; font-size: 10pt; font-weight: bold; }
        .label { font-size: 5pt; text-transform: uppercase; letter-spacing: 1px; margin-top: 1mm; }
        .body { height: 38mm; }
        .photo-area { float: left; width: 29mm; height: 38mm; text-align: center; }
        .artsci-photo { background: #35B3FF; }
        .afc-photo { background: #A37231; }
        .photo { width: 22mm; height: 27mm; object-fit: cover; margin-top: 5mm; border: 1mm solid #fff; border-radius: 2mm; }
        .details { float: left; width: 51mm; height: 38mm; padding: 3mm 3mm 1mm 4mm; color: #0f172a; }
        .name { font-size: 10pt; font-weight: bold; line-height: 1.1; margin: 0 0 2mm; }
        .field-label { color: #64748b; font-size: 5pt; text-transform: uppercase; letter-spacing: 0.5px; }
        .field-value { font-size: 7pt; font-weight: bold; margin: 0 0 1.5mm; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header {{ $branding['css_class'] }}-header">
            @if($branding['logo'])
                <img class="logo" src="{{ $branding['logo'] }}" alt="{{ $branding['name'] }} logo">
            @endif
            <div class="company">{{ $branding['name'] }}<div class="label">Employee ID</div></div>
        </div>
        <div class="body">
            <div class="photo-area {{ $branding['css_class'] }}-photo">
                @if($photoDataUri)
                    <img class="photo" src="{{ $photoDataUri }}" alt="{{ $employee->full_name }}">
                @endif
            </div>
            <div class="details">
                <div class="field-label">Name</div>
                <div class="name">{{ $employee->full_name }}</div>
                <div class="field-label">Employee ID</div>
                <div class="field-value">{{ $employee->employee_id }}</div>
                <div class="field-label">Department</div>
                <div class="field-value">{{ $employee->department?->name ?? '—' }}</div>
                <div class="field-label">Job Title</div>
                <div class="field-value">{{ $employee->position?->title ?? '—' }}</div>
            </div>
        </div>
    </div>
</body>
</html>
