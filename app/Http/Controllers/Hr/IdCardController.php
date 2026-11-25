<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HrSetting;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
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

        return view('admin.hr.employees.id-card-preview', $this->cardViewData($employee));
    }

    public function downloadPdf(Employee $employee): Response
    {
        $this->validateCardRequirements($employee);
        $employee->load(['department', 'position', 'emergencyContacts']);

        $html = view('admin.hr.employees.id-card-pdf', $this->cardViewData($employee))->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $pdf = new Dompdf($options);
        $pdf->setPaper([0, 0, 153.071, 242.646], 'portrait');
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
        if (! $employee->id_card_signature_path || ! Storage::disk('local')->exists($employee->id_card_signature_path)) {
            $missing[] = 'employee signature';
        }

        $hrSignaturePath = HrSetting::getValue($this->hrSignatureSettingKey($employee->company));
        if (! $hrSignaturePath || ! Storage::disk('local')->exists($hrSignaturePath)) {
            $missing[] = 'company HR signature';
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
                'official_name' => 'ARTSCI',
                'css_class' => 'artsci',
                'primary' => '#35B3FF',
                'secondary' => '#0F172A',
                'tagline' => 'BRINGING . DESIGNING . SCIENCE',
                'location' => 'SARS Road by Anticultism Unit',
                'phone' => '09160450776',
                'website' => 'www.artsci.com.ng',
                'website_url' => 'https://www.artsci.com.ng',
                'logo' => $this->assetDataUri(base_path('logos/Artsci Logo REAL 1.png'), 'image/png'),
            ];
        }

        return [
            'name' => 'AFC',
            'official_name' => 'Acie Fraiche Cafe',
            'css_class' => 'afc',
            'primary' => '#A37231',
            'secondary' => '#111827',
            'tagline' => 'FRESHLY CRAFTED SIMPLY DELICIOUS',
            'location' => 'SARS Road, Immediately after the SARS Police Station',
            'phone' => '08143190700',
            'website' => 'www.afc.com.ng',
            'website_url' => 'https://www.afc.com.ng',
            'logo' => $this->assetDataUri(base_path('logos/afclogo.png'), 'image/png'),
        ];
    }

    protected function cardViewData(Employee $employee): array
    {
        $branding = $this->branding($employee);
        $hrSignaturePath = HrSetting::getValue($this->hrSignatureSettingKey($employee->company));

        return [
            'employee' => $employee,
            'branding' => $branding,
            'photoDataUri' => $this->photoDataUri($employee),
            'qrDataUri' => Builder::create()
                ->writer(new SvgWriter)
                ->data($branding['website_url'])
                ->size(180)
                ->margin(0)
                ->build()
                ->getDataUri(),
            'employeeSignatureDataUri' => $this->privateImageDataUri($employee->id_card_signature_path),
            'hrSignatureDataUri' => $this->privateImageDataUri($hrSignaturePath),
            ...$this->emergencyContact($employee),
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

    protected function privateImageDataUri(?string $path): string
    {
        $disk = Storage::disk('local');
        if (! $path || ! $disk->exists($path)) {
            return '';
        }

        $mime = mime_content_type($disk->path($path)) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
    }

    protected function hrSignatureSettingKey(string $company): string
    {
        return 'id_card_hr_signature_'.$company;
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
