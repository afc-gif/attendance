<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\HrSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    public function index()
    {
        $settings = [
            'employee_id_format' => HrSetting::getValue('employee_id_format', 'ART/ AFC plus 6-digit sequence'),
            'probation_default_days' => HrSetting::getValue('probation_default_days', '90'),
            'hr_contact_email' => HrSetting::getValue('hr_contact_email', ''),
        ];
        $hrSignatures = [];
        $disk = Storage::disk('local');

        foreach (['ARTSCI', 'AFC'] as $company) {
            $path = HrSetting::getValue($this->hrSignatureSettingKey($company));
            if ($path && $disk->exists($path)) {
                $mime = mime_content_type($disk->path($path)) ?: 'image/png';
                $hrSignatures[$company] = 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
            } else {
                $hrSignatures[$company] = null;
            }
        }

        return view('admin.hr.settings.index', compact('settings', 'hrSignatures'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'probation_default_days' => ['required', 'integer', 'min:1', 'max:365'],
            'hr_contact_email' => ['nullable', 'email', 'max:255'],
        ]);

        HrSetting::setValue('probation_default_days', $validated['probation_default_days']);
        HrSetting::setValue('hr_contact_email', $validated['hr_contact_email'] ?? '');

        return back()->with('success', 'HR settings updated successfully.');
    }

    public function uploadIdCardHrSignature(Request $request, string $company)
    {
        abort_unless(in_array($company, ['ARTSCI', 'AFC'], true), 404);

        $validated = $request->validate([
            'signature' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);
        $file = $validated['signature'];

        if (! $file->isValid()) {
            return back()->withErrors([
                'signature' => 'The HR signature upload failed. Please select the image again.',
            ]);
        }

        $extension = $file->getMimeType() === 'image/png' ? 'png' : 'jpg';
        $settingKey = $this->hrSignatureSettingKey($company);
        $oldPath = HrSetting::getValue($settingKey);
        $storedPath = $file->storeAs(
            'hr/id-card-signatures/hr/'.$company,
            Str::uuid()->toString().'.'.$extension,
            'local'
        );

        if (! $storedPath) {
            throw new \RuntimeException('Unable to store company HR signature.');
        }

        HrSetting::setValue($settingKey, $storedPath);

        if ($oldPath && $oldPath !== $storedPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return back()->with('success', $company.' HR signature saved.');
    }

    public function deleteIdCardHrSignature(string $company)
    {
        abort_unless(in_array($company, ['ARTSCI', 'AFC'], true), 404);

        $settingKey = $this->hrSignatureSettingKey($company);
        $path = HrSetting::getValue($settingKey);

        if ($path) {
            Storage::disk('local')->delete($path);
        }

        HrSetting::setValue($settingKey, '');

        return back()->with('success', $company.' HR signature removed.');
    }

    protected function hrSignatureSettingKey(string $company): string
    {
        return 'id_card_hr_signature_'.$company;
    }
}
