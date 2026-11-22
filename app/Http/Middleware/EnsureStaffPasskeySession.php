<?php

namespace App\Http\Middleware;

use App\Models\AttendanceCredential;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffPasskeySession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || (! $user->isAfcStaff() && ! $user->isArtsciStaff())) {
            return $next($request);
        }

        $credential = AttendanceCredential::whereKey($request->session()->get('staff_passkey_credential_id'))
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where('approval_status', 'approved')
            ->first();

        if (! $user->isApproved() || ! $user->hrEmployee || $user->hrEmployee->employment_status !== 'active' || ! $credential) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['employee_id' => 'Sign in with your employee ID and approved device to continue.']);
        }

        return $next($request);
    }
}
