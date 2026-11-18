<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Staff Attendance Admin Console')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --sidebar-width: 260px;
        }
        body {
            font-family: 'Manrope', system-ui, -apple-system, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
        }
        .sidebar-transition {
            transition: transform 0.3s ease, width 0.3s ease, margin 0.3s ease;
        }
        .stat-number {
            font-size: clamp(1.125rem, 4vw, 1.875rem);
            line-height: 1.2;
            word-break: break-word;
            overflow-wrap: anywhere;
        }
    </style>
</head>
<body class="bg-slate-100 antialiased flex flex-col h-screen overflow-hidden">

    <!-- Top Navigation Header -->
    <header class="bg-[#0f172a] text-white h-16 flex-shrink-0 sticky top-0 z-40 flex items-center justify-between px-4 md:px-6 shadow-md border-b border-slate-800">
        <div class="flex items-center gap-3">
            <!-- 3 Dashes Hamburger Button (Mobile, Tablet & PC) -->
            <button type="button" id="sidebar-toggle" onclick="toggleSidebar()" class="p-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition focus:outline-none ring-1 ring-slate-700/50" aria-label="Toggle Sidebar Navigation">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <!-- Brand Logo & Title -->
            <a href="{{ route('admin.attendance.index') }}" class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center font-black text-white text-base shadow">
                    SA
                </div>
                <div class="hidden sm:block">
                    <h1 class="font-extrabold text-base text-white leading-none">Staff Attendance</h1>
                    <span class="text-[10px] text-indigo-300 uppercase tracking-widest font-bold">Admin Management Console</span>
                </div>
            </a>
        </div>

        <!-- Top Right Actions -->
        <div class="flex items-center gap-3">
            <a href="{{ route('attendance.dashboard') }}" class="hidden md:flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-800 text-indigo-300 hover:text-white hover:bg-slate-700 transition border border-slate-700/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                <span>Staff Portal</span>
            </a>

            <div class="text-right hidden sm:block">
                <span class="block text-xs font-extrabold text-white leading-none">{{ auth()->user()->name }}</span>
                <span class="text-[10px] text-slate-400 font-bold uppercase">{{ auth()->user()->role }}</span>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-xs font-bold text-slate-300 hover:text-rose-400 transition px-2.5 py-1 rounded-lg hover:bg-slate-800">
                    Logout
                </button>
            </form>
        </div>
    </header>

    <div class="flex-1 flex overflow-hidden">

        <!-- Sidebar Navigation Drawer (Mobile, Tablet & Desktop responsive) -->
        <aside id="admin-sidebar" class="w-64 bg-[#0f172a] text-white flex-shrink-0 flex flex-col justify-between p-4 border-r border-slate-800 sidebar-transition fixed lg:sticky lg:top-0 inset-y-0 left-0 z-30 transform -translate-x-full lg:translate-x-0 top-16 h-[calc(100vh-4rem)] lg:h-full overflow-y-auto">
            <div class="space-y-6">
                <div>
                    <div class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 px-3 mb-2">Main Navigation</div>
                    <nav class="space-y-1">

                        <!-- Overview -->
                        <a href="{{ route('admin.attendance.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-extrabold transition {{ request()->routeIs('admin.attendance.index') ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-4 h-4 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            <span>Dashboard Overview</span>
                        </a>

                        <!-- Staff Analytics & Trends -->
                        <a href="{{ route('admin.attendance.analytics') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-extrabold transition {{ request()->routeIs('admin.attendance.analytics') ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-4 h-4 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span>Staff Analytics & Trends</span>
                        </a>

                        <!-- User Management -->
                        <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-extrabold transition {{ request()->routeIs('admin.users.*') ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-4 h-4 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            <span>User Management</span>
                        </a>

                        <!-- Payroll & Salary -->
                        <a href="{{ route('admin.payroll.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-extrabold transition {{ request()->routeIs('admin.payroll.*') ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-4 h-4 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Payroll & Salary</span>
                        </a>

                        <!-- Shift Schedules -->
                        <a href="{{ route('admin.shifts.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-extrabold transition {{ request()->routeIs('admin.shifts.*') ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-4 h-4 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Shift Templates</span>
                        </a>

                        <!-- Office Networks -->
                        <a href="{{ route('admin.attendance.networks') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-extrabold transition {{ request()->routeIs('admin.attendance.networks') ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-4 h-4 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M810 9a9 9 0 11-18 0 9 9 0 0118 0zM12 15a3 3 0 100-6 3 3 0 000 6z"/></svg>
                            <span>Office Networks</span>
                        </a>

                        <!-- Rules & Settings -->
                        <a href="{{ route('admin.attendance.settings') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-extrabold transition {{ request()->routeIs('admin.attendance.settings') ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-4 h-4 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span>Rules & Settings</span>
                        </a>

                        @if(auth()->user()?->isAdmin())
                            <a href="{{ route('admin.deployments.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-extrabold transition {{ request()->routeIs('admin.deployments.*') ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                                <svg class="w-4 h-4 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 17l4 4 4-4m-4-5v9M20.88 18.09A5 5 0 0018 9h-1.26A8 8 0 104 16.25"/></svg>
                                <span>Deployment Monitor</span>
                            </a>
                        @endif
                    </nav>
                </div>
            </div>

            <!-- Sidebar Footer Info -->
            <div class="pt-4 border-t border-slate-800 text-[11px] text-slate-400">
                <div class="font-bold text-slate-300">Staff Attendance v2.0</div>
                <div>Multi-Company Support</div>
            </div>
        </aside>

        <!-- Overlay backdrop for Mobile & Tablet -->
        <div id="sidebar-backdrop" onclick="toggleSidebar()" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-20 lg:hidden"></div>

        <!-- Main Content Area -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 min-w-0 overflow-y-auto">
            @if(session('success'))
                <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-900 font-semibold rounded-xl flex items-center justify-between shadow-sm text-xs sm:text-sm">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('error') || (isset($errors) && $errors->any()))
                <div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-900 font-semibold rounded-xl shadow-sm text-xs sm:text-sm">
                    @if(session('error')) <div>{{ session('error') }}</div> @endif
                    @if(isset($errors))
                        @foreach($errors->all() as $err) <div>{{ $err }}</div> @endforeach
                    @endif
                </div>
            @endif

            @yield('content')
        </main>

    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('admin-sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');
            const isMobileOrTablet = window.innerWidth < 1024;

            if (isMobileOrTablet) {
                const isHidden = sidebar.classList.contains('-translate-x-full');
                if (isHidden) {
                    sidebar.classList.remove('-translate-x-full');
                    backdrop.classList.remove('hidden');
                } else {
                    sidebar.classList.add('-translate-x-full');
                    backdrop.classList.add('hidden');
                }
            } else {
                // Desktop toggle
                sidebar.classList.toggle('lg:hidden');
            }
        }
    </script>
    @stack('scripts')
</body>
</html>
