@extends('layouts.admin')

@section('title', 'User Details - ' . $user->name)

@section('content')
<div class="space-y-6">

    <!-- Header & Quick Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-slate-900">{{ $user->name }}</h1>
                @if($user->role === 'admin')
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-indigo-100 text-indigo-800">ADMIN</span>
                @elseif($user->role === 'afc_staff')
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-sky-100 text-sky-800">AFC STAFF</span>
                @elseif($user->role === 'artsci_staff')
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-purple-100 text-purple-800">ARTSCI STAFF</span>
                @else
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-slate-100 text-slate-700 uppercase">{{ $user->role_label }}</span>
                @endif

                @if($user->status === 'approved')
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-emerald-100 text-emerald-800">APPROVED</span>
                @elseif($user->status === 'pending')
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-amber-100 text-amber-800 animate-pulse">PENDING</span>
                @elseif($user->status === 'suspended')
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-rose-100 text-rose-800">SUSPENDED</span>
                @else
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-slate-100 text-slate-600 uppercase">{{ $user->status }}</span>
                @endif
            </div>
            <p class="text-xs text-slate-500 mt-1">{{ $user->email }} &bull; Registered {{ $user->created_at ? $user->created_at->format('M j, Y g:i A') : 'N/A' }}</p>
        </div>

        <div class="flex items-center gap-2">
            @if(!$user->isHr() || auth()->user()->isSuperAdmin())
                <a href="{{ route('admin.users.edit', $user) }}" class="bg-[#0f172a] hover:bg-slate-800 text-white font-bold text-xs py-2 px-3.5 rounded-lg shadow">
                    Change Role & Edit Profile
                </a>
            @endif
            <a href="{{ route('admin.attendance.user-history', $user) }}" class="border border-indigo-200 text-indigo-600 hover:bg-indigo-50 font-bold text-xs py-2 px-3.5 rounded-lg">
                View Full Attendance History
            </a>
            <a href="{{ route('admin.users.index') }}" class="text-xs font-bold text-slate-500 hover:text-slate-700">
                &larr; Back to Users
            </a>
        </div>
    </div>

    <!-- Quick Status Action Bar -->
    @if(!$user->isHr() || auth()->user()->isSuperAdmin())
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-3">
        <span class="text-xs font-extrabold text-slate-700">Account Access Control:</span>

        <div class="flex items-center gap-2">
            @if($user->status !== 'approved')
                <form method="POST" action="{{ route('admin.users.status', $user) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="approved">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs py-1.5 px-3 rounded-lg shadow">
                        Approve Account
                    </button>
                </form>
            @endif

            @if($user->status !== 'suspended')
                <form method="POST" action="{{ route('admin.users.status', $user) }}" onsubmit="return confirm('Suspend access for {{ addslashes($user->name) }}?');">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="suspended">
                    <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs py-1.5 px-3 rounded-lg shadow">
                        Suspend Account
                    </button>
                </form>
            @endif

            @if($user->status !== 'rejected')
                <form method="POST" action="{{ route('admin.users.status', $user) }}" onsubmit="return confirm('Reject application for {{ addslashes($user->name) }}?');">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="rejected">
                    <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs py-1.5 px-3 rounded-lg shadow">
                        Reject Account
                    </button>
                </form>
            @endif
        </div>
        @endif
    </div>

    <!-- Punctuality Stats Matrix -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Punctuality Rate</span>
            <span class="text-2xl font-black text-indigo-600 mt-1 block">{{ $stats['punctuality_rate'] }}%</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">{{ $stats['total_present'] }} days present</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Late This Week</span>
            <span class="text-2xl font-black text-amber-600 mt-1 block">{{ $stats['late_week'] }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">Current week</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Late This Month</span>
            <span class="text-2xl font-black text-amber-600 mt-1 block">{{ $stats['late_month'] }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">Current month</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Late This Year</span>
            <span class="text-2xl font-black text-rose-600 mt-1 block">{{ $stats['late_year'] }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">Current year</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Late All-Time</span>
            <span class="text-2xl font-black text-slate-800 mt-1 block">{{ $stats['late_all_time'] }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">Historical total</span>
        </div>
    </div>

    <!-- Passkey Devices & Recent Attendance Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Registered Devices -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <h3 class="font-extrabold text-sm text-slate-900">Passkey Security Devices</h3>
                <span class="text-xs text-slate-500 font-semibold">{{ $credentials->count() }} device(s)</span>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($credentials as $cred)
                    <div class="py-2.5 flex items-center justify-between text-xs">
                        <div>
                            <div class="font-bold text-slate-900">{{ $cred->device_name ?: 'WebAuthn Device' }}</div>
                            <div class="text-[10px] text-slate-400">
                                Status: <strong>{{ strtoupper($cred->approval_status) }}</strong> &bull; 
                                {{ $cred->is_active ? 'Active' : 'Inactive' }}
                            </div>
                        </div>

                        @if($cred->isPending())
                            <form method="POST" action="{{ route('admin.attendance.credentials.approve', $cred) }}">
                                @csrf
                                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] py-1 px-2.5 rounded shadow">
                                    Approve Device
                                </button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="py-4 text-center text-xs text-slate-400">No passkey devices registered.</div>
                @endforelse
            </div>
        </div>

        <!-- Recent Attendance Log -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <h3 class="font-extrabold text-sm text-slate-900">Recent Attendance Activity</h3>
                <a href="{{ route('admin.attendance.user-history', $user) }}" class="text-xs font-bold text-indigo-600 hover:underline">View All</a>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($records as $r)
                    <div class="py-2.5 flex items-center justify-between text-xs">
                        <div>
                            <div class="font-bold text-slate-900">{{ $r->attendance_date->format('D, M j, Y') }}</div>
                            <div class="text-[10px] text-slate-400">In: {{ $r->check_in_at ? $r->check_in_at->format('g:i A') : '--' }} &bull; Out: {{ $r->check_out_at ? $r->check_out_at->format('g:i A') : '--' }}</div>
                        </div>

                        <div>
                            @if($r->isLate())
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-amber-100 text-amber-800">Late</span>
                            @elseif($r->status === 'present')
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-emerald-100 text-emerald-800">Present</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-slate-100 text-slate-700 uppercase">{{ $r->status }}</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="py-4 text-center text-xs text-slate-400">No recent attendance records.</div>
                @endforelse
            </div>
        </div>

    </div>

</div>
@endsection
