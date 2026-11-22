@extends('layouts.admin')

@section('title', 'Create User Account - Admin Console')

@section('content')
<div class="space-y-6 max-w-2xl mx-auto">

    <!-- Top Navigation -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900">Create User Account</h1>
            <p class="text-xs text-slate-500">Add a new staff member, administrator, or HR account</p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
            &larr; Back to Users List
        </a>
    </div>

    <!-- Create Form Card -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-bold uppercase text-slate-700 mb-1">Full Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Jane Doe" class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-700 mb-1">Email Address</label>
                <input type="email" name="email" value="{{ old('email') }}" required placeholder="e.g. jane.doe@company.com" class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-700 mb-1">Role</label>
                    <select name="role" required class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-800">
                        <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin (Full System Access)</option>
                        @if(auth()->user()->isSuperAdmin())
                            <option value="hr" {{ old('role') === 'hr' ? 'selected' : '' }}>HR (HR Management Only)</option>
                        @endif
                        <option value="afc_staff" {{ old('role', 'afc_staff') === 'afc_staff' ? 'selected' : '' }}>AFC Staff</option>
                        <option value="artsci_staff" {{ old('role') === 'artsci_staff' ? 'selected' : '' }}>ARTSCI Staff</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-700 mb-1">Account Status</label>
                    <select name="status" required class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-800">
                        <option value="approved" {{ old('status', 'approved') === 'approved' ? 'selected' : '' }}>Approved (Active Access)</option>
                        <option value="pending" {{ old('status') === 'pending' ? 'selected' : '' }}>Pending Review</option>
                        <option value="suspended" {{ old('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                        <option value="rejected" {{ old('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-700 mb-1">Password</label>
                    <input type="password" name="password" required minlength="8" placeholder="Minimum 8 characters" class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-xs font-bold">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-700 mb-1">Confirm Password</label>
                    <input type="password" name="password_confirmation" required minlength="8" placeholder="Re-enter password" class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-xs font-bold">
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('admin.users.index') }}" class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-800">Cancel</a>
                <button type="submit" class="bg-[#0f172a] hover:bg-slate-800 text-white font-extrabold text-xs py-2.5 px-5 rounded-xl shadow">Create Account</button>
            </div>
        </form>
    </div>

</div>
@endsection
