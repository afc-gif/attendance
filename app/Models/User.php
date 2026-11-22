<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

class User extends Authenticatable implements PasskeyUser
{
    use HasFactory, Notifiable, PasskeyAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->role)) {
                $user->role = 'afc_staff';
            }
            if (empty($user->status)) {
                $user->status = 'approved';
            }
        });
    }

    public function attendanceCredentials()
    {
        return $this->hasMany(AttendanceCredential::class);
    }

    public function activeAttendanceCredential()
    {
        return $this->hasOne(AttendanceCredential::class)->where('is_active', true)->latestOfMany();
    }

    public function hasActiveAttendanceCredential(): bool
    {
        return $this->attendanceCredentials()->where('is_active', true)->exists();
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function staffProfile()
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function getOrCreateStaffProfile(): StaffProfile
    {
        $profile = $this->staffProfile()->first();
        if ($profile) {
            return $profile;
        }

        return $this->staffProfile()->create([
            'base_salary' => 0.00,
            'off_days' => [0], // Default Sunday off
            'grace_period_minutes' => 15,
        ]);
    }

    public function salaryDeductions()
    {
        return $this->hasMany(SalaryDeduction::class);
    }

    public function payrollArchives()
    {
        return $this->hasMany(PayrollArchive::class);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin', 'manager', 'executive'], true);
    }

    public function isAfcStaff(): bool
    {
        return $this->role === 'afc_staff';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function hrEmployee()
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

    public function isArtsciStaff(): bool
    {
        return $this->role === 'artsci_staff';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function getPasskeyUsername(): string
    {
        return $this->hrEmployee?->employee_id ?? (string) $this->getAuthIdentifier();
    }

    public function getPasskeyDisplayName(): string
    {
        return $this->hrEmployee?->employee_id ?? (string) $this->getAuthIdentifier();
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'admin' => 'Admin',
            'afc_staff' => 'AFC Staff',
            'artsci_staff' => 'ARTSCI Staff',
            default => ucfirst(str_replace('_', ' ', $this->role)),
        };
    }
}
