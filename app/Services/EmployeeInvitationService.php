<?php

namespace App\Services;

use App\Mail\EmployeeInvitationMail;
use App\Models\Employee;
use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeInvitationService
{
    public const EXPIRATION_DAYS = 7;

    /**
     * Prepare an invitation and create a pending attendance account if needed.
     *
     * @return array{invitation: EmployeeInvitation, token: string}|null
     */
    public function prepare(Employee $employee, bool $force = false): ?array
    {
        if (! $employee->user_id) {
            if (! $employee->email) {
                return null;
            }

            if (User::where('email', $employee->email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'An account already uses this email. Select that account in the login account field, or use a different email.',
                ]);
            }

            $role = $employee->company === 'ARTSCI' ? 'artsci_staff' : 'afc_staff';
            $user = User::create([
                'name' => $employee->full_name,
                'email' => $employee->email,
                'password' => Str::random(64),
                'role' => $role,
                'status' => 'pending',
            ]);

            $employee->user()->associate($user);
            $employee->save();
        } else {
            $user = $employee->user()->firstOrFail();
        }

        $expectedRole = $employee->company === 'ARTSCI' ? 'artsci_staff' : 'afc_staff';
        if ($user->role !== $expectedRole) {
            throw ValidationException::withMessages([
                'user_id' => 'The linked account role does not match the employee company.',
            ]);
        }

        if ($user->isApproved() && ! $force) {
            return null;
        }
        if ($user->status !== 'pending') {
            throw ValidationException::withMessages([
                'invitation' => 'Only pending staff accounts can be invited. Contact an administrator to review this account.',
            ]);
        }

        $token = Str::random(64);
        $invitation = EmployeeInvitation::updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'token_hash' => hash('sha256', $token),
                'invited_to' => $user->email,
                'expires_at' => now()->addDays(self::EXPIRATION_DAYS),
                'sent_at' => null,
                'accepted_at' => null,
                'created_by' => Auth::id(),
            ]
        );

        return compact('invitation', 'token');
    }

    public function send(Employee $employee, EmployeeInvitation $invitation, string $token, string $origin): void
    {
        $activationUrl = rtrim($origin, '/').route('employee-invitations.show', ['token' => $token], false);

        Mail::to($invitation->invited_to)->send(new EmployeeInvitationMail(
            $employee,
            $activationUrl,
            self::EXPIRATION_DAYS,
        ));

        $invitation->update(['sent_at' => now()]);
    }
}
