@extends('layouts.admin')

@section('title', 'Deployment Monitor - Admin Console')

@section('content')
    @php
        $deploymentDataUrl = route('admin.deployments.data');
    @endphp
    <div id="deployment-monitor" data-data-url="{{ $deploymentDataUrl }}" class="space-y-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900">Deployment Monitor</h1>
                <p class="mt-1 text-xs text-slate-500">Office server deployment status and diagnostic events.</p>
            </div>
            <p class="text-[11px] font-semibold text-slate-500">
                Refreshes automatically every 15 seconds
                <span id="deployment-refresh-time" class="ml-1"></span>
            </p>
        </div>

        <div id="deployment-refresh-error" class="hidden rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs font-semibold text-rose-800" role="status"></div>

        <section class="grid gap-4 md:grid-cols-3" aria-label="Deployment summary">
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-extrabold uppercase tracking-wide text-slate-500">Current status</p>
                <p id="deployment-current-status" class="mt-2 text-xl font-black text-slate-900">
                    {{ $snapshot['current']['status'] ?? 'No deployments yet' }}
                </p>
                <p id="deployment-current-commit" class="mt-1 break-all font-mono text-xs text-slate-600">
                    {{ $snapshot['current']['commit'] ?? 'Commit unavailable' }}
                </p>
                <p id="deployment-current-time" class="mt-2 text-[11px] text-slate-500">
                    @if(isset($snapshot['current']['started_at']))
                        {{ ($snapshot['current']['status'] === 'running' ? 'Started ' : 'Completed ') . \Illuminate\Support\Carbon::parse($snapshot['current']['finished_at'] ?? $snapshot['current']['started_at'])->timezone(config('app.timezone'))->format('M j, Y g:i:s A T') }}
                    @else
                        No deployment recorded
                    @endif
                </p>
            </article>

            <article class="rounded-xl border border-emerald-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-extrabold uppercase tracking-wide text-emerald-700">Last successful deployment</p>
                <p id="deployment-success-commit" class="mt-2 break-all font-mono text-sm font-bold text-slate-900">
                    {{ $snapshot['last_successful']['commit'] ?? 'None recorded' }}
                </p>
                <p id="deployment-success-time" class="mt-1 text-[11px] text-slate-500">
                    @if(isset($snapshot['last_successful']['finished_at']))
                        {{ \Illuminate\Support\Carbon::parse($snapshot['last_successful']['finished_at'])->timezone(config('app.timezone'))->format('M j, Y g:i:s A T') }}
                    @else
                        -
                    @endif
                </p>
            </article>

            <article class="rounded-xl border border-rose-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-extrabold uppercase tracking-wide text-rose-700">Last failed deployment</p>
                <p id="deployment-failure-commit" class="mt-2 break-all font-mono text-sm font-bold text-slate-900">
                    {{ $snapshot['last_failed']['commit'] ?? 'None recorded' }}
                </p>
                <p id="deployment-failure-time" class="mt-1 text-[11px] text-slate-500">
                    @if(isset($snapshot['last_failed']['finished_at']))
                        {{ \Illuminate\Support\Carbon::parse($snapshot['last_failed']['finished_at'])->timezone(config('app.timezone'))->format('M j, Y g:i:s A T') }}
                    @else
                        -
                    @endif
                </p>
                <p id="deployment-failure-error" class="mt-2 break-words text-xs text-rose-800">{{ $snapshot['last_failed']['error'] ?? '' }}</p>
            </article>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-extrabold text-slate-900">Deployment history</h2>
                <p class="mt-1 text-[11px] text-slate-500">Stage events and failure details are read from private server storage.</p>
            </div>
            <div id="deployment-history" class="divide-y divide-slate-100">
                @forelse($snapshot['deployments'] as $deployment)
                    <details class="group px-5 py-4" {{ $loop->first ? 'open' : '' }}>
                        <summary class="flex cursor-pointer list-none flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <span class="flex min-w-0 flex-wrap items-center gap-2">
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-extrabold uppercase {{ $deployment['status'] === 'succeeded' ? 'bg-emerald-100 text-emerald-800' : ($deployment['status'] === 'failed' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
                                    {{ $deployment['status'] }}
                                </span>
                                <span class="break-all font-mono text-xs font-bold text-slate-800">{{ $deployment['commit'] ?? 'Commit unavailable' }}</span>
                            </span>
                            <span class="shrink-0 text-[11px] text-slate-500">
                                {{ \Illuminate\Support\Carbon::parse($deployment['started_at'])->timezone(config('app.timezone'))->format('M j, Y g:i:s A T') }}
                            </span>
                        </summary>
                        <div class="mt-4 space-y-3 border-l-2 border-slate-200 pl-4">
                            @foreach($deployment['events'] as $event)
                                <div class="text-xs">
                                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                        <span class="font-bold uppercase text-slate-700">{{ str_replace('_', ' ', $event['stage']) }}</span>
                                        <span class="text-[10px] text-slate-400">{{ \Illuminate\Support\Carbon::parse($event['timestamp'])->timezone(config('app.timezone'))->format('M j, Y g:i:s A T') }}</span>
                                    </div>
                                    <p class="mt-1 break-words text-slate-600">{{ $event['message'] }}</p>
                                    @if($event['error'])
                                        <pre class="mt-1 whitespace-pre-wrap break-words rounded-lg bg-rose-50 p-3 font-sans text-[11px] text-rose-800">{{ $event['error'] }}</pre>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </details>
                @empty
                    <p class="px-5 py-8 text-center text-xs text-slate-500">No deployment events have been recorded.</p>
                @endforelse
            </div>
        </section>
    </div>

    <script>
        (() => {
            const dataUrl = document.getElementById('deployment-monitor').dataset.dataUrl;
            const statusClasses = {
                succeeded: 'text-emerald-700',
                failed: 'text-rose-700',
                running: 'text-amber-700',
            };
            const displayDate = (value) => value
                ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'medium' }).format(new Date(value))
                : '-';
            const setText = (id, value) => {
                document.getElementById(id).textContent = value || '';
            };
            const element = (tag, className, text) => {
                const node = document.createElement(tag);
                node.className = className;
                node.textContent = text || '';
                return node;
            };

            const renderHistory = (deployments) => {
                const container = document.getElementById('deployment-history');
                container.replaceChildren();

                if (deployments.length === 0) {
                    container.append(element('p', 'px-5 py-8 text-center text-xs text-slate-500', 'No deployment events have been recorded.'));
                    return;
                }

                deployments.forEach((deployment, index) => {
                    const details = document.createElement('details');
                    details.className = 'group px-5 py-4';
                    details.open = index === 0;

                    const summary = element('summary', 'flex cursor-pointer list-none flex-col gap-2 sm:flex-row sm:items-center sm:justify-between');
                    const identity = element('span', 'flex min-w-0 flex-wrap items-center gap-2');
                    const statusClass = deployment.status === 'succeeded'
                        ? 'bg-emerald-100 text-emerald-800'
                        : (deployment.status === 'failed' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800');
                    identity.append(element('span', `rounded-full px-2.5 py-1 text-[10px] font-extrabold uppercase ${statusClass}`, deployment.status));
                    identity.append(element('span', 'break-all font-mono text-xs font-bold text-slate-800', deployment.commit || 'Commit unavailable'));
                    summary.append(identity, element('span', 'shrink-0 text-[11px] text-slate-500', displayDate(deployment.started_at)));

                    const events = element('div', 'mt-4 space-y-3 border-l-2 border-slate-200 pl-4');
                    deployment.events.forEach((event) => {
                        const item = element('div', 'text-xs');
                        const heading = element('div', 'flex flex-wrap items-baseline gap-x-2 gap-y-1');
                        heading.append(
                            element('span', 'font-bold uppercase text-slate-700', event.stage.replaceAll('_', ' ')),
                            element('span', 'text-[10px] text-slate-400', displayDate(event.timestamp))
                        );
                        item.append(heading, element('p', 'mt-1 break-words text-slate-600', event.message));
                        if (event.error) {
                            item.append(element('pre', 'mt-1 whitespace-pre-wrap break-words rounded-lg bg-rose-50 p-3 font-sans text-[11px] text-rose-800', event.error));
                        }
                        events.append(item);
                    });

                    details.append(summary, events);
                    container.append(details);
                });
            };

            const renderSnapshot = (snapshot) => {
                const current = snapshot.current;
                const successful = snapshot.last_successful;
                const failed = snapshot.last_failed;

                setText('deployment-current-status', current?.status || 'No deployments yet');
                document.getElementById('deployment-current-status').className =
                    `mt-2 text-xl font-black ${statusClasses[current?.status] || 'text-slate-900'}`;
                setText('deployment-current-commit', current?.commit || 'Commit unavailable');
                setText('deployment-current-time', current
                    ? `${current.status === 'running' ? 'Started' : 'Completed'} ${displayDate(current.finished_at || current.started_at)}`
                    : 'No deployment recorded');
                setText('deployment-success-commit', successful?.commit || 'None recorded');
                setText('deployment-success-time', displayDate(successful?.finished_at));
                setText('deployment-failure-commit', failed?.commit || 'None recorded');
                setText('deployment-failure-time', displayDate(failed?.finished_at));
                setText('deployment-failure-error', failed?.error || '');
                renderHistory(snapshot.deployments || []);
                setText('deployment-refresh-time', `Last checked ${displayDate(new Date().toISOString())}`);
            };

            const refresh = async () => {
                const errorNotice = document.getElementById('deployment-refresh-error');
                try {
                    const response = await fetch(dataUrl, {
                        headers: { Accept: 'application/json' },
                        cache: 'no-store',
                        credentials: 'same-origin',
                    });
                    if (!response.ok) {
                        throw new Error(`Refresh failed with HTTP ${response.status}.`);
                    }
                    renderSnapshot(await response.json());
                    errorNotice.classList.add('hidden');
                    errorNotice.textContent = '';
                } catch (error) {
                    errorNotice.textContent = `Unable to refresh deployment information: ${error.message}`;
                    errorNotice.classList.remove('hidden');
                }
            };

            setText('deployment-refresh-time', `Last checked ${displayDate(new Date().toISOString())}`);
            window.setInterval(refresh, 15000);
        })();
    </script>
@endsection
