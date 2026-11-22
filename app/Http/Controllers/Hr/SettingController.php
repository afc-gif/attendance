<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\HrSetting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = [
            'employee_id_format' => HrSetting::getValue('employee_id_format', 'ART/ AFC plus 6-digit sequence'),
            'probation_default_days' => HrSetting::getValue('probation_default_days', '90'),
            'hr_contact_email' => HrSetting::getValue('hr_contact_email', ''),
        ];

        return view('admin.hr.settings.index', compact('settings'));
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
}
