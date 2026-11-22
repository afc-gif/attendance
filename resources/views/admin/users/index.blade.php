@extends('layouts.admin')

@section('title', 'User Management - Admin Console')

@section('content')
<div class="space-y-6">

    <!-- Header & Create Action -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900">User Management</h1>
            <p class="text-xs text-slate-500">Manage employee accounts, assign roles, approve pending staff, and control system access</p>
        </div>

        <a href="{{ route('admin.users.create') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs py-2.5 px-4 rounded-xl shadow flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Create New User</span>
        </a>
    </div>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Total Users</span>
            <span class="text-2xl font-black text-slate-900 mt-1 block">{{ $totalUsers }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">All registered accounts</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Administrators</span>
            <span class="text-2xl font-black text-indigo-600 mt-1 block">{{ $adminCount }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">Admin role</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">AFC Staff</span>
            <span class="text-2xl font-black text-sky-600 mt-1 block">{{ $afcCount }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">AFC staff members</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">ARTSCI Staff</span>
            <span class="text-2xl font-black text-purple-600 mt-1 block">{{ $artsciCount }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">ARTSCI staff members</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Pending Review</span>
            <span class="text-2xl font-black text-amber-600 mt-1 block">{{ $pendingCount }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">Awaiting admin review</span>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
        <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[200px]">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by name or email..." class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500">
            </div>

            <select name="role" onchange="this.form.submit()" class="px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold text-slate-700">
                <option value="">All Roles</option>
                <option value="admin" {{ $roleFilter === 'admin' ? 'selected' : '' }}>Admin</option>
                @if(auth()->user()->isSuperAdmin())
                    <option value="hr" {{ $roleFilter === 'hr' ? 'selected' : '' }}>HR</option>
                @endif
                <option value="afc_staff" {{ $roleFilter === 'afc_staff' ? 'selected' : '' }}>AFC Staff</option>
                <option value="artsci_staff" {{ $roleFilter === 'artsci_staff' ? 'selected' : '' }}>ARTSCI Staff</option>
            </select>

            <select name="status" onchange="this.form.submit()" class="px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold text-slate-700">
                <option value="">All Statuses</option>
                <option value="approved" {{ $statusFilter === 'approved' ? 'selected' : '' }}>Approved</option>
                <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="rejected" {{ $statusFilter === 'rejected' ? 'selected' : '' }}>Rejected</option>
                <option value="suspended" {{ $statusFilter === 'suspended' ? 'selected' : '' }}>Suspended</option>
            </select>

            <button type="submit" class="bg-[#0f172a] hover:bg-slate-800 text-white font-bold text-xs py-2 px-4 rounded-lg shadow">Filter</button>

            @if($search || $roleFilter || $statusFilter)
                <a href="{{ route('admin.users.index') }}" class="text-xs font-bold text-rose-600 hover:underline">Clear Filters</a>
            @endif
        </form>
    </div>

    <!-- User Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h3 class="font-extrabold text-sm text-slate-800">User Accounts List</h3>
            <span class="text-xs text-slate-500 font-semibold">{{ $users->total() }} user(s) found</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="bg-slate-100 uppercase tracking-wider font-extrabold text-[10px] text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="p-3.5">User</th>
                        <th class="p-3.5">Role</th>
                        <th class="p-3.5">Status</th>
                        <th class="p-3.5">Registered</th>
                        <th class="p-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($users as $u)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="p-3.5">
                                <a href="{{ route('admin.users.show', $u) }}" class="font-bold text-slate-900 hover:text-indigo-600 block">
                                    {{ $u->name }}
                                </a>
                                <div class="text-[11px] text-slate-400">{{ $u->email }}</div>
                            </td>
                            <td class="p-3.5">
                                @if($u->role === 'admin')
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-extrabold bg-indigo-100 text-indigo-800">ADMIN</span>
                                @elseif($u->role === 'afc_staff')
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-extrabold bg-sky-100 text-sky-800">AFC STAFF</span>
                                @elseif($u->role === 'artsci_staff')
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-extrabold bg-purple-100 text-purple-800">ARTSCI STAFF</span>
                                @else
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-extrabold bg-slate-100 text-slate-700">{{ strtoupper($u->role_label) }}</span>
                                @endif
                            </td>
                            <td class="p-3.5">
                                @if($u->status === 'approved')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800">Approved</span>
                                @elseif($u->status === 'pending')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-800 animate-pulse">Pending Review</span>
                                @elseif($u->status === 'suspended')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-rose-100 text-rose-800">Suspended</span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-slate-100 text-slate-600 uppercase">{{ $u->status }}</span>
                                @endif
                            </td>
                            <td class="p-3.5 text-slate-500">
                                {{ $u->created_at ? $u->created_at->format('M j, Y') : 'N/A' }}
                            </td>
                            <td class="p-3.5 text-right space-x-2">
                                <a href="{{ route('admin.users.show', $u) }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">View</a>
                                @if(!$u->isHr() || auth()->user()->isSuperAdmin())
                                    <a href="{{ route('admin.users.edit', $u) }}" class="text-xs font-bold text-slate-600 hover:text-slate-900 border border-slate-300 px-2 py-1 rounded">Edit Role</a>
                                @endif

                                @if($u->id !== auth()->id() && (!$u->isHr() || auth()->user()->isSuperAdmin()))
                                    <form method="POST" action="{{ route('admin.users.destroy', $u) }}" class="inline" onsubmit="return confirm('Permanently delete {{ addslashes($u->name) }} account?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-bold text-rose-600 hover:text-rose-800">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-slate-400 text-xs">No users found matching search criteria.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-slate-100">
            {{ $users->links() }}
        </div>
    </div>
</div>
@endsection
