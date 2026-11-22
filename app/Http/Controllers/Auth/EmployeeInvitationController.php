<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmployeeInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EmployeeInvitationController extends Controller
{
    public function show(string $token)
    {
        $invitation = $this->findUsableInvitation($token);

        return view('auth.employee-invitation', compact('invitation', 'token'));
    }

    public function accept(Request $request, string $token)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $tokenHash = hash('sha256', $token);

        $user = DB::transaction(function () use ($tokenHash, $validated) {
            $invitation = EmployeeInvitation::where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (! $invitation || $invitation->accepted_at || $invitation->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'invitation' => 'This invitation is invalid or has expired. Ask HR to send a new invitation.',
                ]);
            }

            $employee = $invitation->employee()->with('user')->first();
            $user = $employee?->user;
            $expectedRole = $employee?->company === 'ARTSCI' ? 'artsci_staff' : 'afc_staff';

            if (! $employee || ! $user || $user->role !== $expectedRole || $user->status !== 'pending' || $user->email !== $invitation->invited_to) {
                throw ValidationException::withMessages([
                    'invitation' => 'This invitation can no longer be used. Contact HR for assistance.',
                ]);
            }

            $user->forceFill([
                'password' => Hash::make($validated['password']),
                'status' => 'approved',
                'email_verified_at' => now(),
            ])->save();
            $invitation->update(['accepted_at' => now()]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('attendance.dashboard')
            ->with('success', 'Your account is active. Register your attendance device to continue.');
    }

    protected function findUsableInvitation(string $token): EmployeeInvitation
    {
        $invitation = EmployeeInvitation::with('employee.user')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        $employee = $invitation?->employee;
        $user = $employee?->user;
        $expectedRole = $employee?->company === 'ARTSCI' ? 'artsci_staff' : 'afc_staff';

        if (! $invitation || $invitation->accepted_at || $invitation->expires_at->isPast()
            || ! $employee || ! $user || $user->status !== 'pending'
            || $user->role !== $expectedRole || $user->email !== $invitation->invited_to) {
            abort(response()->view('auth.employee-invitation-expired', status: 410));
        }

        return $invitation;
    }
}
