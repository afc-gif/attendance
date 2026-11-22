@extends('layouts.app')

@section('title', 'Sign In - Staff Attendance')

@section('content')
<div class="bg-white rounded-2xl shadow-xl border border-slate-200/80 p-6 md:p-8 mt-6 max-w-xl mx-auto">
    <div class="text-center mb-6">
        <div class="w-14 h-14 bg-[#0f172a] rounded-2xl mx-auto flex items-center justify-center text-white font-black text-xl shadow-lg mb-3">SA</div>
        <h1 class="text-2xl font-extrabold text-slate-900">Sign in to Attendance</h1>
        <p class="text-xs text-slate-500 mt-1">Staff use their employee ID and registered device.</p>
    </div>

    <div id="passkey-alert" role="status" aria-live="polite" class="hidden mb-4 rounded-xl border p-3 text-sm"></div>

    <section class="space-y-4">
        <h2 class="text-sm font-extrabold text-slate-900">Staff sign-in</h2>
        <form id="staff-passkey-form"
              data-login-options="{{ route('staff.passkeys.login.options') }}"
              data-login="{{ route('staff.passkeys.login') }}"
              data-register-options="{{ route('staff.passkeys.register.options') }}"
              data-register="{{ route('staff.passkeys.register') }}"
              class="space-y-3">
            @csrf
            <label for="employee_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Employee ID</label>
            <input id="employee_id" name="employee_id" type="text" value="{{ old('employee_id') }}" required maxlength="50" autocomplete="username" autocapitalize="characters" placeholder="ART-483921" class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm">
            <button type="button" id="staff-passkey-login" class="w-full bg-[#0f172a] hover:bg-slate-800 text-white font-extrabold text-sm py-3.5 px-4 rounded-xl shadow-md">Sign in with this device</button>
            <button type="button" id="staff-passkey-register" class="w-full border border-indigo-200 text-indigo-700 hover:bg-indigo-50 font-bold text-sm py-3 px-4 rounded-xl">First time? Register this device</button>
        </form>
        <p class="text-[11px] leading-relaxed text-slate-500">Device registration uses your phone or computer screen lock/biometric. New devices require administrator approval before sign-in.</p>
    </section>

    <details class="mt-7 border-t border-slate-100 pt-4">
        <summary class="cursor-pointer text-xs font-bold text-slate-600">Administrator / HR sign-in</summary>
        <form method="POST" action="{{ route('login') }}" class="space-y-3 mt-4">
            @csrf
            <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Email Address</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required autocomplete="username" class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm">
            <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Password</label>
            <input type="password" name="password" id="password" required autocomplete="current-password" class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm">
            <label class="flex items-center gap-2 text-xs text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-indigo-600">
                <span>Remember me</span>
            </label>
            <button type="submit" class="w-full bg-slate-700 hover:bg-slate-800 text-white font-bold text-sm py-3 px-4 rounded-xl">Sign in as administrator</button>
        </form>
    </details>
</div>
@endsection

@push('scripts')
<script>
    const passkeyAlert = document.getElementById('passkey-alert');
    const passkeyForm = document.getElementById('staff-passkey-form');
    const employeeIdInput = document.getElementById('employee_id');
    const csrfToken = passkeyForm.querySelector('input[name="_token"]').value;

    function showPasskeyAlert(message, success = false) {
        passkeyAlert.textContent = message;
        passkeyAlert.className = 'mb-4 rounded-xl border p-3 text-sm ' + (success
            ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
            : 'border-rose-200 bg-rose-50 text-rose-900');
        passkeyAlert.classList.remove('hidden');
    }

    function decodeBase64Url(value) {
        const padded = value.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - value.length % 4) % 4);
        const binary = atob(padded);
        return Uint8Array.from(binary, character => character.charCodeAt(0));
    }

    function encodeBase64Url(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (const byte of bytes) binary += String.fromCharCode(byte);
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function credentialToJson(credential) {
        const response = credential.response;
        const result = {
            id: credential.id,
            rawId: encodeBase64Url(credential.rawId),
            type: credential.type,
            response: {
                clientDataJSON: encodeBase64Url(response.clientDataJSON)
            },
            clientExtensionResults: credential.getClientExtensionResults(),
            authenticatorAttachment: credential.authenticatorAttachment
        };

        if (response.attestationObject) {
            result.response.attestationObject = encodeBase64Url(response.attestationObject);
            if (response.getTransports) result.response.transports = response.getTransports();
        } else {
            result.response.authenticatorData = encodeBase64Url(response.authenticatorData);
            result.response.signature = encodeBase64Url(response.signature);
            result.response.userHandle = response.userHandle ? encodeBase64Url(response.userHandle) : null;
        }

        return result;
    }

    async function postJson(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(payload)
        });
        const data = await response.json();

        if (!response.ok) {
            const firstError = Object.values(data.errors || {}).flat()[0];
            throw new Error(firstError || data.message || 'The request could not be completed.');
        }

        return data;
    }

    function employeeId() {
        const value = employeeIdInput.value.trim().toUpperCase();
        if (!value) throw new Error('Enter the employee ID provided by HR.');
        employeeIdInput.value = value;
        return value;
    }

    async function signInWithPasskey() {
        if (!window.isSecureContext || !window.PublicKeyCredential || !navigator.credentials) {
            throw new Error('Device sign-in requires a supported browser and a secure connection (HTTPS or localhost).');
        }

        const data = await postJson(passkeyForm.dataset.loginOptions, { employee_id: employeeId() });
        const options = data.options;
        options.challenge = decodeBase64Url(options.challenge);
        options.allowCredentials = (options.allowCredentials || []).map(item => ({ ...item, id: decodeBase64Url(item.id) }));

        const credential = await navigator.credentials.get({ publicKey: options });
        if (!credential) throw new Error('Device verification was cancelled.');

        const result = await postJson(passkeyForm.dataset.login, {
            employee_id: employeeId(),
            credential: credentialToJson(credential)
        });

        window.location.assign(result.redirect);
    }

    async function registerPasskey() {
        if (!window.isSecureContext || !window.PublicKeyCredential || !navigator.credentials) {
            throw new Error('Device registration requires a supported browser and a secure connection (HTTPS or localhost).');
        }

        const data = await postJson(passkeyForm.dataset.registerOptions, { employee_id: employeeId() });
        const options = data.options;
        options.challenge = decodeBase64Url(options.challenge);
        options.user.id = decodeBase64Url(options.user.id);
        options.excludeCredentials = (options.excludeCredentials || []).map(item => ({ ...item, id: decodeBase64Url(item.id) }));

        const credential = await navigator.credentials.create({ publicKey: options });
        if (!credential) throw new Error('Device registration was cancelled.');

        const result = await postJson(passkeyForm.dataset.register, {
            name: navigator.userAgent.includes('(') ? navigator.userAgent.split(')')[0].split('(')[1] : 'Staff device',
            credential: credentialToJson(credential)
        });
        showPasskeyAlert(result.message, true);
    }

    async function runPasskeyAction(button, action) {
        button.disabled = true;
        try {
            await action();
        } catch (error) {
            showPasskeyAlert(error.message || 'Device verification failed.');
        } finally {
            button.disabled = false;
        }
    }

    document.getElementById('staff-passkey-login').addEventListener('click', event => runPasskeyAction(event.currentTarget, signInWithPasskey));
    document.getElementById('staff-passkey-register').addEventListener('click', event => runPasskeyAction(event.currentTarget, registerPasskey));
</script>
@endpush
