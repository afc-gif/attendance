<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeAccountService
{
    public function ensureAccount(Employee $employee): User
    {
        $role = $employee->company === 'ARTSCI' ? 'artsci_staff' : 'afc_staff';
        $user = $employee->user;

        if ($user) {
            if (! in_array($user->role, ['artsci_staff', 'afc_staff'], true)) {
                throw ValidationException::withMessages([
                    'user_id' => 'An employee can only be linked to an ARTSCI or AFC staff account.',
                ]);
            }

            if ($user->role !== $role) {
                $user->role = $role;
            }

            if ($user->email !== $employee->email && $employee->email) {
                $emailInUse = User::where('email', $employee->email)
                    ->where('id', '!=', $user->getKey())
                    ->exists();

                if ($emailInUse) {
                    throw ValidationException::withMessages([
                        'email' => 'Another account already uses this email address.',
                    ]);
                }

                $user->email = $employee->email;
            }

            $user->name = $employee->full_name;
            $user->save();

            return $user;
        }

        $email = $employee->email ?: strtolower($employee->employee_id).'@staff.invalid';

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'An account already uses this email. Select that account in the login account field, or use a different email.',
            ]);
        }

        $user = User::create([
            'name' => $employee->full_name,
            'email' => $email,
            'password' => Str::random(64),
            'role' => $role,
            'status' => 'pending',
        ]);

        $employee->user()->associate($user);
        $employee->save();

        return $user;
    }
}
