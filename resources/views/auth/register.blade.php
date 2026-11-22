@extends('layouts.app')

@section('title', 'Invitation Required - Staff Attendance')

@section('content')
<div class="bg-white rounded-2xl shadow-xl border border-slate-200/80 p-6 md:p-8 mt-6 max-w-xl mx-auto text-center">
    <h1 class="text-2xl font-extrabold text-slate-900">Staff accounts are invitation-only</h1>
    <p class="mt-3 text-sm text-slate-600">Your HR team will create your employee profile and email you a secure account activation link.</p>
    <a href="{{ route('login') }}" class="inline-block mt-6 bg-[#0f172a] text-white font-extrabold text-sm py-3 px-5 rounded-xl">Sign in</a>
</div>
@endsection
