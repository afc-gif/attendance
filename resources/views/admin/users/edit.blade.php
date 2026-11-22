@extends('layouts.admin')

@section('title', 'Edit User - ' . $user->name)

@section('content')
<div class="space-y-6 max-w-2xl mx-auto">

    <!-- Top Navigation -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900">Edit User Role & Profile</h1>
            <p class="text-xs text-slate-500">Update role and account status for {{ $user->name }}</p>
        </div>
        <a href="{{ route('admin.users.show', $user) }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
            &larr; Back to Profile
        </a>
    </div>

    <!-- Edit Form Card -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-bold uppercase text-slate-700 mb-1">Full Name</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-700 mb-1">Email Address</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-indigo-700 mb-1">User Role</label>
                    <select name="role" required class="w-full px-3 py-2.5 border-2 border-indigo-300 rounded-xl text-xs font-bold text-indigo-900 bg-indigo-50/30">
                        <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Admin (Full System Access)</option>
                        @if(auth()->user()->isSuperAdmin())
                            <option value="hr" {{ old('role', $user->role) === 'hr' ? 'selected' : '' }}>HR (HR Management Only)</option>
                        @endif
                        <option value="afc_staff" {{ old('role', $user->role) === 'afc_staff' ? 'selected' : '' }}>AFC Staff</option>
                        <option value="artsci_staff" {{ old('role', $user->role) === 'artsci_staff' ? 'selected' : '' }}>ARTSCI Staff</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-700 mb-1">Account Status</label>
                    <select name="status" required class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-800">
                        <option value="approved" {{ old('status', $user->status) === 'approved' ? 'selected' : '' }}>Approved (Active Access)</option>
                        <option value="pending" {{ old('status', $user->status) === 'pending' ? 'selected' : '' }}>Pending Review</option>
                        <option value="suspended" {{ old('status', $user->status) === 'suspended' ? 'selected' : '' }}>Suspended</option>
                        <option value="rejected" {{ old('status', $user->status) === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 space-y-3">
                <div class="font-extrabold text-xs text-slate-700 uppercase">Change Password (Optional)</div>
                <p class="text-[11px] text-slate-500">Leave blank if you do not wish to change the user password.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-500 mb-1">New Password</label>
                        <input type="password" name="password" minlength="8" placeholder="Minimum 8 characters" class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-xs font-bold">
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Confirm New Password</label>
                        <input type="password" name="password_confirmation" minlength="8" placeholder="Re-enter new password" class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-xs font-bold">
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('admin.users.show', $user) }}" class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-800">Cancel</a>
                <button type="submit" class="bg-[#0f172a] hover:bg-slate-800 text-white font-extrabold text-xs py-2.5 px-5 rounded-xl shadow">Save Role & Profile Changes</button>
            </div>
        </form>
    </div>

</div>
@endsection
