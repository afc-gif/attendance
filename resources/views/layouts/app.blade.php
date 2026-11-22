<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- PWA Web App Capabilities -->
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192x192.png">
    <meta name="theme-color" content="#0f172a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Staff Attendance">

    <title>@yield('title', 'Staff Attendance PWA')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root {
            --brand-dark: #0f172a;
            --brand-accent: #c046e5;
        }
        body {
            font-family: 'Manrope', system-ui, -apple-system, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
            -webkit-user-select: none;
        }
        /* Mobile Touch & Haptic Micro-Interactions */
        .btn-touch {
            min-height: 54px;
            touch-action: manipulation;
            transition: transform 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
        }
        .btn-touch:active {
            transform: scale(0.97);
        }
        /* Bottom Safe Area spacing for iPhones & gesture bars */
        .pb-safe {
            padding-bottom: max(1.25rem, env(safe-area-inset-bottom));
        }
        .pt-safe {
            padding-top: max(0.75rem, env(safe-area-inset-top));
        }
    </style>
</head>
<body class="min-h-full flex flex-col justify-between antialiased selection:bg-indigo-500 selection:text-white bg-slate-50">

    <!-- Mobile Sticky Top Header -->
    <header class="bg-[#0f172a] text-white shadow-lg sticky top-0 z-40 pt-safe">
        <div class="max-w-lg mx-auto px-4 py-3 flex items-center justify-between">
            <a href="{{ route('attendance.dashboard') }}" class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center font-black text-white text-base shadow-md ring-2 ring-indigo-400/30">
                    SA
                </div>
                <div>
                    <span class="font-black text-base tracking-wide block leading-none">Staff Attendance</span>
                    <span class="text-[10px] text-indigo-300 tracking-wider uppercase font-extrabold">Official Verification PWA</span>
                </div>
            </a>

            @auth
            <div class="flex items-center gap-2">
                @if(auth()->user()->canAccessAdminConsole())
                <a href="{{ route('admin.attendance.index') }}" class="text-[11px] font-extrabold bg-indigo-600 hover:bg-indigo-500 px-3 py-1.5 rounded-lg text-white shadow-sm flex items-center gap-1 transition">
                    <svg class="w-3.5 h-3.5 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
                    <span>Admin</span>
                </a>
                @endif

                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition" title="Logout">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </button>
                </form>
            </div>
            @endauth
        </div>
    </header>

    <!-- Main Content Container (Mobile-first max-w-lg) -->
    <main class="flex-1 max-w-lg mx-auto w-full px-4 py-5 mb-16 sm:mb-6">

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-bold rounded-2xl flex items-start gap-2.5 shadow-sm">
                <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error') || $errors->any())
            <div class="mb-4 p-4 bg-rose-50 border border-rose-200 text-rose-900 text-xs font-bold rounded-2xl flex items-start gap-2.5 shadow-sm">
                <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    @if(session('error')) <div>{{ session('error') }}</div> @endif
                    @foreach($errors->all() as $err) <div>{{ $err }}</div> @endforeach
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Mobile Fixed Bottom Navigation Bar (PWA Mobile Fit) -->
    @auth
    <nav class="fixed bottom-0 inset-x-0 bg-white border-t border-slate-200 z-30 pb-safe shadow-2xl">
        <div class="max-w-lg mx-auto px-6 h-16 flex items-center justify-around">

            <!-- Check In Dashboard -->
            <a href="{{ route('attendance.dashboard') }}" class="flex flex-col items-center gap-1 text-xs font-bold transition {{ request()->routeIs('attendance.dashboard') ? 'text-indigo-600' : 'text-slate-400 hover:text-slate-600' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-[10px] uppercase tracking-wider">Check In</span>
            </a>

            <!-- History -->
            <a href="{{ route('attendance.history') }}" class="flex flex-col items-center gap-1 text-xs font-bold transition {{ request()->routeIs('attendance.history') ? 'text-indigo-600' : 'text-slate-400 hover:text-slate-600' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <span class="text-[10px] uppercase tracking-wider">My History</span>
            </a>

            <!-- Payroll -->
            <a href="{{ route('staff.payroll.index') }}" class="flex flex-col items-center gap-1 text-xs font-bold transition {{ request()->routeIs('staff.payroll.*') ? 'text-indigo-600' : 'text-slate-400 hover:text-slate-600' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-[10px] uppercase tracking-wider">Payroll</span>
            </a>

            <!-- Admin Console (If Admin) -->
            @if(auth()->user()->canAccessAdminConsole())
            <a href="{{ route('admin.attendance.index') }}" class="flex flex-col items-center gap-1 text-xs font-bold text-slate-400 hover:text-indigo-600 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <span class="text-[10px] uppercase tracking-wider">Admin</span>
            </a>
            @endif

        </div>
    </nav>
    @endauth

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(err => console.log('SW reg error:', err));
            });
        }
    </script>
    @stack('scripts')
</body>
</html>
