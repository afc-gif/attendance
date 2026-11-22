@extends('layouts.app')

@section('title', 'Activate Staff Account')

@section('content')
<div class="bg-white rounded-2xl shadow-xl border border-slate-200/80 p-6 md:p-8 mt-6 max-w-xl mx-auto">
    <div class="text-center mb-6">
        <h1 class="text-2xl font-extrabold text-slate-900">Confirm your staff account</h1>
        <p class="text-sm text-slate-500 mt-2">Welcome, {{ $invitation->employee->full_name }}. Choose a password to activate your attendance account.</p>
        <p class="text-xs text-slate-500 mt-1">{{ $invitation->invited_to }}</p>
    </div>

    <form method="POST" action="{{ route('employee-invitations.accept', $token) }}" class="space-y-4">
        @csrf
        <div>
            <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">New password</label>
            <input id="password" name="password" type="password" required minlength="12" autocomplete="new-password" class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm">
        </div>
        <div>
            <label for="password_confirmation" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password" class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm">
        </div>
        <button type="submit" class="w-full bg-[#0f172a] hover:bg-slate-800 text-white font-extrabold text-sm py-3.5 px-4 rounded-xl shadow-md">Confirm and Activate Account</button>
    </form>
</div>
@endsection
