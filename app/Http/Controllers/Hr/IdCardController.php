<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class IdCardController extends Controller
{
    public function generate(Employee $employee)
    {
        $this->validateCardRequirements($employee);

        return redirect()->route('admin.hr.employees.id-card.preview', $employee);
    }

    public function preview(Employee $employee)
    {
        $this->validateCardRequirements($employee);
        $employee->load(['department', 'position', 'emergencyContacts']);

        return view('admin.hr.employees.id-card-preview', [
            'employee' => $employee,
            'branding' => $this->branding($employee),
            'photoDataUri' => $this->photoDataUri($employee),
            ...$this->emergencyContact($employee),
        ]);
    }

    public function downloadPdf(Employee $employee): Response
    {
        $this->validateCardRequirements($employee);
        $employee->load(['department', 'position', 'emergencyContacts']);

        $html = view('admin.hr.employees.id-card-pdf', [
            'employee' => $employee,
            'branding' => $this->branding($employee),
            'photoDataUri' => $this->photoDataUri($employee),
            ...$this->emergencyContact($employee),
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $pdf = new Dompdf($options);
        $pdf->setPaper([0, 0, 242.646, 153.071], 'portrait');
        $pdf->loadHtml($html);
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$employee->employee_id.'-id-card.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    protected function validateCardRequirements(Employee $employee): void
    {
        $missing = [];

        if (! $employee->first_name || ! $employee->last_name) {
            $missing[] = 'full name';
        }
        if (! $employee->employee_id) {
            $missing[] = 'employee ID';
        }
        if (! $employee->department_id) {
            $missing[] = 'department';
        }
        if (! $employee->position_id) {
            $missing[] = 'job title';
        }
        if (! $employee->passport_photo_path) {
            $missing[] = 'passport photograph';
        } elseif (! Storage::disk('local')->exists($employee->passport_photo_path)) {
            $missing[] = 'valid passport photograph file';
        }

        if ($missing !== []) {
            abort(422, 'Cannot generate ID card. Missing: '.implode(', ', $missing).'.');
        }
    }

    protected function branding(Employee $employee): array
    {
        if ($employee->company === 'ARTSCI') {
            return [
                'name' => 'ARTSCI',
                'css_class' => 'artsci',
                'primary' => '#35B3FF',
                'secondary' => '#0F172A',
                'logo' => $this->assetDataUri(base_path('logos/Artsci Logo REAL 1.png'), 'image/png'),
            ];
        }

        return [
            'name' => 'AFC',
            'css_class' => 'afc',
            'primary' => '#A37231',
            'secondary' => '#111827',
            'logo' => $this->assetDataUri(base_path('logos/afclogo.png'), 'image/png'),
        ];
    }

    protected function photoDataUri(Employee $employee): string
    {
        $disk = Storage::disk('local');
        if (! $employee->passport_photo_path || ! $disk->exists($employee->passport_photo_path)) {
            return '';
        }

        $mime = $employee->passport_photo_mime ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($employee->passport_photo_path));
    }

    protected function emergencyContact(Employee $employee): array
    {
        $contact = $employee->emergencyContacts->firstWhere('is_primary', true);

        return [
            'emergencyContactName' => $contact?->name ?: ($employee->emergency_contact_name ?: $employee->next_of_kin),
            'emergencyContactPhone' => $contact?->phone ?: ($employee->emergency_contact_phone ?: $employee->next_of_kin_phone),
        ];
    }

    protected function assetDataUri(string $path, string $fallbackMime): string
    {
        if (! is_file($path)) {
            return '';
        }

        $mime = mime_content_type($path) ?: $fallbackMime;

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    }
}
