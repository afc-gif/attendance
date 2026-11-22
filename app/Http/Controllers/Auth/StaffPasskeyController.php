<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AttendanceAuditLog;
use App\Models\AttendanceCredential;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Http\Requests\PasskeyRegistrationRequest;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Support\WebAuthn;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Webauthn\PublicKeyCredentialDescriptor;

class StaffPasskeyController extends Controller
{
    public function registrationOptions(Request $request, GenerateRegistrationOptions $generate)
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'string', 'max:50'],
        ]);
        $user = $this->userForEmployeeId($validated['employee_id']);

        if (! in_array($user->status, ['pending', 'approved'], true)) {
            throw ValidationException::withMessages([
                'employee_id' => 'This account is not eligible for device registration. Contact HR.',
            ]);
        }

        if ($this->hasActivePasskeyCredential($user)) {
            throw ValidationException::withMessages([
                'employee_id' => 'A device is already registered. Contact HR or an administrator if you need to replace it.',
            ]);
        }

        $options = $generate($user);
        $request->session()->put('passkey.registration_options', WebAuthn::toJson($options));
        $request->session()->put('passkey.registration_user_id', $user->id);

        return response()->json(['options' => WebAuthn::toBrowserArray($options)]);
    }

    public function register(PasskeyRegistrationRequest $request, StorePasskey $storePasskey)
    {
        $userId = $request->session()->get('passkey.registration_user_id');
        $user = $userId ? User::find($userId) : null;

        if (! $user || ! $user->hrEmployee || $user->hrEmployee->employment_status !== 'active') {
            $request->session()->forget(['passkey.registration_user_id', 'passkey.registration_options']);

            throw ValidationException::withMessages([
                'credential' => 'This device registration could not be completed. Start again with your employee ID.',
            ]);
        }

        if ($this->hasActivePasskeyCredential($user)) {
            throw ValidationException::withMessages([
                'credential' => 'A device is already registered. Contact HR or an administrator if you need to replace it.',
            ]);
        }

        $options = $request->registrationOptions();

        try {
            $credential = DB::transaction(function () use ($request, $storePasskey, $user, $options) {
                /** @var Passkey $passkey */
                $passkey = $storePasskey(
                    $user,
                    $request->string('name')->toString(),
                    $request->credential(),
                    $options,
                );

                $credential = AttendanceCredential::create([
                    'user_id' => $user->id,
                    'credential_id' => $passkey->credential_id,
                    'public_key' => json_encode($passkey->credential, JSON_THROW_ON_ERROR),
                    'attestation_format' => 'webauthn',
                    'sign_count' => 0,
                    'device_name' => $passkey->name,
                    'user_agent' => mb_substr($request->userAgent() ?? '', 0, 500),
                    'is_active' => true,
                    'approval_status' => 'pending',
                    'registered_at' => now(),
                ]);

                AttendanceAuditLog::logEvent(
                    eventType: 'device_registration',
                    affectedUser: $user,
                    newValues: [
                        'credential_id' => $credential->id,
                        'device_name' => $credential->device_name,
                        'approval_status' => 'pending',
                    ],
                    reason: 'Passkey registration submitted; awaiting administrator approval',
                );

                return $credential;
            });
        } catch (InvalidPasskeyException $exception) {
            report($exception);

            return response()->json([
                'message' => 'The device could not be verified. Please restart registration and try again.',
            ], 422);
        }

        $request->session()->forget('passkey.registration_user_id');

        return response()->json([
            'message' => 'Device registered and awaiting administrator approval. You can sign in after it is approved.',
        ], 202);
    }

    public function loginOptions(Request $request, GenerateVerificationOptions $generate)
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'string', 'max:50'],
        ]);
        $user = $this->userForEmployeeId($validated['employee_id']);

        if ($user->status !== 'approved') {
            throw ValidationException::withMessages([
                'employee_id' => 'Your staff account is not active yet. Contact HR or wait for device approval.',
            ]);
        }

        $credentialIds = $this->approvedCredentialIds($user);
        if ($credentialIds === []) {
            throw ValidationException::withMessages([
                'employee_id' => 'No approved device is registered for this account. Register a device or contact an administrator.',
            ]);
        }

        $options = $generate($user);
        $options->allowCredentials = array_map(
            fn (string $credentialId) => PublicKeyCredentialDescriptor::create(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                Base64UrlSafe::decodeNoPadding($credentialId),
            ),
            $credentialIds,
        );

        $request->session()->put('passkey.verification_options', WebAuthn::toJson($options));
        $request->session()->put('passkey.login_user_id', $user->id);

        return response()->json(['options' => WebAuthn::toBrowserArray($options)]);
    }

    public function login(PasskeyVerificationRequest $request, VerifyPasskey $verify)
    {
        $userId = $request->session()->pull('passkey.login_user_id');
        $user = $userId ? User::find($userId) : null;

        if (! $user || $user->status !== 'approved' || ! $user->hrEmployee || $user->hrEmployee->employment_status !== 'active') {
            $request->session()->forget('passkey.verification_options');

            throw ValidationException::withMessages([
                'credential' => 'Unable to sign in with this employee ID and device.',
            ]);
        }

        $options = $request->verificationOptions();

        try {
            $passkey = $verify($request->credential(), $options, $user);
        } catch (InvalidPasskeyException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Device verification failed. Check your employee ID and try again.',
            ], 422);
        }

        $credential = AttendanceCredential::where('user_id', $user->id)
            ->where('credential_id', $passkey->credential_id)
            ->where('is_active', true)
            ->where('approval_status', 'approved')
            ->first();

        if (! $credential) {
            throw ValidationException::withMessages([
                'credential' => 'This device is not approved for this account. Contact an administrator.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('staff_passkey_credential_id', $credential->id);

        $credential->update(['last_used_at' => now()]);
        AttendanceAuditLog::logEvent(
            eventType: 'staff_passkey_login',
            actor: $user,
            affectedUser: $user,
            newValues: ['credential_id' => $credential->id],
            reason: 'Staff signed in with an approved device passkey',
        );

        return response()->json([
            'redirect' => route('attendance.dashboard'),
        ]);
    }

    protected function userForEmployeeId(string $employeeId): User
    {
        $employee = Employee::with('user')
            ->where('employee_id', strtoupper(trim($employeeId)))
            ->where('employment_status', 'active')
            ->first();

        $user = $employee?->user;
        $expectedRole = $employee?->company === 'ARTSCI' ? 'artsci_staff' : 'afc_staff';

        if (! $employee || ! $user || $user->role !== $expectedRole) {
            throw ValidationException::withMessages([
                'employee_id' => 'This employee ID is not available for device sign-in. Contact HR.',
            ]);
        }

        return $user;
    }

    protected function hasActivePasskeyCredential(User $user): bool
    {
        return AttendanceCredential::where('user_id', $user->id)
            ->where('is_active', true)
            ->whereIn('credential_id', $user->passkeys()->select('credential_id'))
            ->exists();
    }

    /**
     * @return list<string>
     */
    protected function approvedCredentialIds(User $user): array
    {
        return AttendanceCredential::where('user_id', $user->id)
            ->where('is_active', true)
            ->where('approval_status', 'approved')
            ->whereIn('credential_id', $user->passkeys()->select('credential_id'))
            ->pluck('credential_id')
            ->all();
    }
}
