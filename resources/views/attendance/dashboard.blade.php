@extends('layouts.app')

@section('title', 'Staff Attendance PWA')

@section('content')
<div class="space-y-4">

    <!-- PWA Install App Card (Visible on Android & iOS when not running as installed standalone app) -->
    <div id="pwa-install-container" class="hidden bg-indigo-600 text-white p-4 rounded-3xl shadow-lg relative overflow-hidden">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center font-black text-white shrink-0">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                </div>
                <div>
                    <h3 class="font-extrabold text-sm leading-tight">Install Staff App</h3>
                    <p class="text-[11px] text-indigo-100 font-medium">Add to phone home screen for quick check-in</p>
                </div>
            </div>

            <button type="button" id="btn-install-pwa" onclick="handlePwaInstallClick()" class="bg-white text-indigo-950 hover:bg-indigo-50 font-black text-xs py-2 px-3.5 rounded-xl shadow transition shrink-0 active:scale-95">
                Install App
            </button>
        </div>
    </div>

    <!-- Greeting & Live Clock Mobile Hero Card -->
    <div class="bg-[#0f172a] text-white p-5 sm:p-6 rounded-3xl shadow-xl relative overflow-hidden">
        <div class="absolute right-0 top-0 w-36 h-36 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-[11px] text-indigo-300 font-extrabold uppercase tracking-widest">{{ $today->format('D, M j, Y') }}</span>
                @if($user->role === 'admin')
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-indigo-500/30 text-indigo-200 border border-indigo-400/30">ADMIN</span>
                @elseif($user->role === 'artsci_staff')
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-purple-500/30 text-purple-200 border border-purple-400/30">ARTSCI STAFF</span>
                @else
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-sky-500/30 text-sky-200 border border-sky-400/30">AFC STAFF</span>
                @endif
            </div>

            <div>
                <h2 class="text-xl sm:text-2xl font-black tracking-tight">
                    @php
                        $hour = \Carbon\Carbon::now($tz ?? 'Africa/Lagos')->hour;
                        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
                    @endphp
                    {{ $greeting }}, {{ explode(' ', $user->name)[0] }}
                </h2>
                <p class="text-xs text-slate-300 mt-0.5 font-medium">Verify your daily office attendance</p>
            </div>

            <!-- Live Digital Clock Widget -->
            <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between">
                <span class="text-[10px] uppercase tracking-wider font-extrabold text-slate-400">Local Time ({{ $tz ?? 'WAT' }})</span>
                <span id="live-clock" class="text-lg font-black font-mono tracking-wider text-indigo-300">--:--:-- --</span>
            </div>
        </div>
    </div>

    <!-- Network & Device Diagnostics Badges -->
    <div class="grid grid-cols-2 gap-2.5">
        <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-2.5">
            <div class="w-3 h-3 rounded-full shrink-0 {{ $isNetworkVerified ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500' }}"></div>
            <div class="min-w-0">
                <div class="text-[9px] uppercase tracking-widest font-extrabold text-slate-400">Network</div>
                <div class="text-xs font-bold text-slate-800 truncate" title="{{ $clientIp }}">
                    {{ $isNetworkVerified ? 'Office IP' : 'Unverified (' . $clientIp . ')' }}
                </div>
            </div>
        </div>

        <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-2.5">
            <div class="w-3 h-3 rounded-full shrink-0 {{ $activeCredential && $activeCredential->isApproved() ? 'bg-emerald-500' : ($activeCredential && $activeCredential->isPending() ? 'bg-amber-500 animate-pulse' : 'bg-rose-500') }}"></div>
            <div class="min-w-0">
                <div class="text-[9px] uppercase tracking-widest font-extrabold text-slate-400">Passkey Device</div>
                <div class="text-xs font-bold text-slate-800 truncate">
                    @if($activeCredential && $activeCredential->isApproved())
                        Approved
                    @elseif($activeCredential && $activeCredential->isPending())
                        Pending Admin
                    @else
                        Not Registered
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Salary & Balance Widget -->
    @if(isset($payrollSummary))
    <div class="bg-gradient-to-r from-slate-900 to-indigo-950 text-white p-4 rounded-2xl shadow-sm flex items-center justify-between gap-3 min-w-0 overflow-hidden">
        <div class="min-w-0 flex-1">
            <div class="text-[10px] font-bold uppercase tracking-wider text-indigo-300 truncate">Est. Salary Balance (This Month)</div>
            <div class="text-base sm:text-lg md:text-xl font-black text-emerald-400 mt-0.5 font-mono tracking-tight truncate">₦{{ number_format($payrollSummary['net_salary'], 2) }}</div>
            <div class="text-[10px] text-slate-300 font-medium mt-0.5 truncate">
                Base: ₦{{ number_format($payrollSummary['base_salary']) }}
                @if($payrollSummary['total_deductions'] > 0)
                    &bull; <span class="text-rose-400">Deductions: -₦{{ number_format($payrollSummary['total_deductions']) }}</span>
                @endif
            </div>
        </div>
        <a href="{{ route('staff.payroll.index') }}" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition shrink-0">
            Statement &rarr;
        </a>
    </div>
    @endif

    <!-- Device Pending Admin Approval Notice -->
    @if($activeCredential && $activeCredential->isPending())
    <div class="bg-amber-50 border border-amber-300 p-4 rounded-2xl shadow-sm text-amber-900 flex items-start gap-3">
        <svg class="w-6 h-6 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            <h4 class="font-extrabold text-xs text-amber-950">Device Pending Approval</h4>
            <p class="text-[11px] mt-0.5 text-amber-800 leading-relaxed">
                Your device passkey has been submitted and is awaiting administrator approval before check-in is enabled.
            </p>
        </div>
    </div>
    @endif

    <!-- Attendance Action & Status Card -->
    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/80 shadow-md space-y-4">

        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest">Today's Status</span>
                <div class="mt-0.5">
                    @if(!$record)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-black bg-slate-100 text-slate-700">
                            Not Checked In
                        </span>
                    @elseif($record->isCheckedOut())
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-black bg-slate-100 text-slate-600">
                            Checked Out
                        </span>
                    @elseif($record->isLate())
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-black bg-amber-100 text-amber-800">
                            Checked In (Late)
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-black bg-emerald-100 text-emerald-800">
                            Checked In (On Time)
                        </span>
                    @endif
                </div>
            </div>

            <div class="text-right">
                <span class="text-[9px] font-extrabold text-slate-400 uppercase tracking-widest block">Expected Shift</span>
                <span class="text-xs font-extrabold text-slate-800">{{ $expectedArrival }}</span>
            </div>
        </div>

        <!-- Check In / Out Time Stamps -->
        <div class="grid grid-cols-2 gap-3">
            <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                <span class="text-[9px] font-extrabold text-slate-400 uppercase tracking-wider block">Check In Time</span>
                <span class="text-sm font-black text-slate-800 mt-0.5 block">
                    {{ $record && $record->check_in_at ? $record->check_in_at->setTimezone($tz ?? 'Africa/Lagos')->format('g:i A') : '--:--' }}
                </span>
            </div>
            <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                <span class="text-[9px] font-extrabold text-slate-400 uppercase tracking-wider block">Check Out Time</span>
                <span class="text-sm font-black text-slate-800 mt-0.5 block">
                    {{ $record && $record->check_out_at ? $record->check_out_at->setTimezone($tz ?? 'Africa/Lagos')->format('g:i A') : '--:--' }}
                </span>
            </div>
        </div>

        <!-- Feedback Alert Box -->
        <div id="attendance-alert" class="hidden p-3.5 rounded-2xl text-xs font-bold"></div>

        <!-- Touch Primary Actions -->
        @php
            $canAct = $activeCredential && $activeCredential->isApproved();
        @endphp

        @if(!$record || !$record->check_in_at)
            <button type="button" id="btn-check-in" onclick="performAttendanceAction('check-in')"
                class="w-full bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-black text-base py-4 px-6 rounded-2xl shadow-lg transition btn-touch flex items-center justify-center gap-2.5 disabled:opacity-50"
                {{ !$canAct ? 'disabled' : '' }}>
                <svg class="w-6 h-6 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                <span>Check In</span>
            </button>
        @elseif($record && $record->check_in_at && !$record->check_out_at)
            <button type="button" id="btn-check-out" onclick="performAttendanceAction('check-out')"
                class="w-full bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-black text-base py-4 px-6 rounded-2xl shadow-lg transition btn-touch flex items-center justify-center gap-2.5 disabled:opacity-50"
                {{ !$canAct ? 'disabled' : '' }}>
                <svg class="w-6 h-6 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                <span>Check Out</span>
            </button>
        @else
            <div class="text-center p-4 bg-emerald-50 text-emerald-800 rounded-2xl border border-emerald-200 font-extrabold text-xs flex items-center justify-center gap-2">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                <span>Completed Attendance for Today</span>
            </div>
        @endif

    </div>
</div>

<!-- iOS Add to Home Screen Instructions Modal -->
<div id="ios-install-modal" class="hidden fixed inset-0 bg-slate-900/70 backdrop-blur-sm z-50 flex items-end sm:items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl space-y-4 text-center border border-slate-200">
        <div class="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-2xl mx-auto flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
        </div>

        <div>
            <h3 class="font-extrabold text-base text-slate-900">Install on iPhone / iPad</h3>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                Follow these 2 steps in Safari to add the Staff Attendance app to your home screen:
            </p>
        </div>

        <div class="bg-slate-50 p-4 rounded-2xl text-left space-y-3 text-xs text-slate-700 font-bold border border-slate-100">
            <div class="flex items-center gap-3">
                <span class="w-6 h-6 rounded-full bg-indigo-600 text-white flex items-center justify-center text-[10px] font-black shrink-0">1</span>
                <span>Tap the <strong>Share</strong> button <svg class="w-4 h-4 inline text-indigo-600 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg> at the bottom of Safari.</span>
            </div>

            <div class="flex items-center gap-3">
                <span class="w-6 h-6 rounded-full bg-indigo-600 text-white flex items-center justify-center text-[10px] font-black shrink-0">2</span>
                <span>Scroll down and tap <strong>Add to Home Screen</strong> <svg class="w-4 h-4 inline text-indigo-600 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>.</span>
            </div>
        </div>

        <button type="button" onclick="document.getElementById('ios-install-modal').classList.add('hidden')" class="w-full bg-[#0f172a] text-white font-extrabold text-xs py-3 px-4 rounded-xl shadow">
            Got It
        </button>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Live Digital Clock JS
    function updateLiveClock() {
        const now = new Date();
        const clockEl = document.getElementById('live-clock');
        if (clockEl) {
            clockEl.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        }
    }
    setInterval(updateLiveClock, 1000);
    updateLiveClock();

    // PWA Native Install Prompt Handler (Android & iOS)
    let deferredPrompt = null;

    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    document.addEventListener('DOMContentLoaded', () => {
        // Hide install prompt if already running inside installed standalone app
        if (!isStandalone) {
            document.getElementById('pwa-install-container')?.classList.remove('hidden');
        }
    });

    // Android Chrome `beforeinstallprompt` Event Capture
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        if (!isStandalone) {
            document.getElementById('pwa-install-container')?.classList.remove('hidden');
        }
    });

    // User taps "Install App"
    function handlePwaInstallClick() {
        if (deferredPrompt) {
            // Native Android prompt
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    document.getElementById('pwa-install-container')?.classList.add('hidden');
                }
                deferredPrompt = null;
            });
        } else if (isIOS) {
            // Show iOS Safari "Add to Home Screen" instructions
            document.getElementById('ios-install-modal')?.classList.remove('hidden');
        } else {
            alert('To install, open your browser menu (...) and tap "Add to Home Screen" or "Install App".');
        }
    }

    window.addEventListener('appinstalled', () => {
        document.getElementById('pwa-install-container')?.classList.add('hidden');
        deferredPrompt = null;
    });

    function showAlert(type, message) {
        const alertBox = document.getElementById('attendance-alert');
        if (!alertBox) return;

        alertBox.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-900', 'border-emerald-200', 'bg-rose-50', 'text-rose-900', 'border-rose-200');

        if (type === 'success') {
            alertBox.classList.add('bg-emerald-50', 'text-emerald-900', 'border', 'border-emerald-200');
        } else {
            alertBox.classList.add('bg-rose-50', 'text-rose-900', 'border', 'border-rose-200');
        }

        alertBox.innerText = message;
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (navigator.vibrate) navigator.vibrate(200);
    }

    function setActionButtonState(btn, isLoading, label) {
        if (!btn) return;

        btn.disabled = isLoading;
        const labelEl = btn.querySelector('span');
        if (labelEl) {
            if (!btn.dataset.originalLabel) {
                btn.dataset.originalLabel = labelEl.textContent;
            }
            labelEl.textContent = isLoading ? label : btn.dataset.originalLabel;
        }
    }

    async function performAttendanceAction(actionType) {
        const btnId = actionType === 'check-in' ? 'btn-check-in' : 'btn-check-out';
        const btn = document.getElementById(btnId);
        setActionButtonState(btn, true, 'Processing...');

        try {
            const url = actionType === 'check-in' ? "{{ route('attendance.check-in') }}" : "{{ route('attendance.check-out') }}";
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({})
            });

            const data = await response.json();
            if (response.ok && data.success) {
                showAlert('success', data.message + (data.check_in_time ? ' at ' + data.check_in_time : ''));
                setTimeout(() => window.location.reload(), 1200);
            } else {
                showAlert('error', data.message || 'Attendance action failed.');
            }
        } catch (err) {
            showAlert('error', err.message || 'Attendance action failed.');
        } finally {
            setActionButtonState(btn, false);
        }
    }
</script>
@endpush
