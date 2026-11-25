<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'employee_id', 'company', 'first_name', 'middle_name', 'last_name',
        'date_of_birth', 'gender', 'nationality', 'residential_address', 'phone', 'email',
        'religion', 'marital_status', 'next_of_kin', 'next_of_kin_relationship',
        'next_of_kin_phone', 'next_of_kin_address', 'emergency_contact_name',
        'emergency_contact_phone', 'emergency_contact_relationship',
        'department_id', 'position_id', 'reporting_manager_id', 'employment_type',
        'employment_date', 'probation_start_date', 'probation_end_date',
        'employment_status', 'confirmation_status',
        'passport_photo_path', 'passport_photo_original_name',
        'passport_photo_mime', 'passport_photo_size', 'id_card_signature_path',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'employment_date' => 'date',
            'probation_start_date' => 'date',
            'probation_end_date' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(HrDepartment::class, 'department_id');
    }

    public function position()
    {
        return $this->belongsTo(HrPosition::class, 'position_id');
    }

    public function reportingManager()
    {
        return $this->belongsTo(Employee::class, 'reporting_manager_id');
    }

    public function emergencyContacts()
    {
        return $this->hasMany(EmployeeEmergencyContact::class);
    }

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function employmentHistories()
    {
        return $this->hasMany(EmployeeEmploymentHistory::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(collect([$this->first_name, $this->middle_name, $this->last_name])->filter()->implode(' '));
    }
}
