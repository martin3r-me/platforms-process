<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[['label' => 'Prozesse']]">
            <x-slot name="left">
                <a href="{{ route('process.processes.list') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 text-xs text-[var(--nx-muted)] hover:text-[var(--nx-text)] transition-colors">
                    @svg('heroicon-o-list-bullet', 'w-4 h-4')
                    <span>Listenansicht</span>
                </a>
            </x-slot>

            <x-nx-button variant="primary" size="sm" href="{{ route('process.processes.list') }}" wire:navigate>
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neuer Prozess</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        {{-- KPI Stat Tiles --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <x-nx-stat label="Gesamt" :value="$this->totalProcesses" icon="heroicon-o-arrow-path" :href="route('process.processes.list')" />
            @php
                $activeCount = collect($this->statusCounts)->firstWhere('status', \Platform\Process\Enums\ProcessStatus::ACTIVE)['count'] ?? 0;
                $draftCount = collect($this->statusCounts)->firstWhere('status', \Platform\Process\Enums\ProcessStatus::DRAFT)['count'] ?? 0;
            @endphp
            <x-nx-stat label="Aktiv" :value="$activeCount" icon="heroicon-o-check-circle" :href="route('process.processes.index.status', 'active')" />
            <x-nx-stat label="Entwurf" :value="$draftCount" icon="heroicon-o-pencil-square" :href="route('process.processes.index.status', 'draft')" />
            <x-nx-stat label="Automatisierung" :value="$this->automationScore" icon="heroicon-o-cpu-chip" hint="Ø LLM-Score %" />
        </div>

        {{-- Charts Row --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            {{-- Status-Verteilung Donut --}}
            <div class="bg-[color:var(--nx-surface)] rounded-2xl shadow-[var(--nx-shadow-card)] p-6" wire:ignore>
                <h3 class="text-sm font-semibold text-[var(--nx-text)] mb-4">Status-Verteilung</h3>
                <div x-data="{
                    chart: null,
                    init() {
                        const data = @js(collect($this->statusCounts)->filter(fn($s) => $s['count'] > 0)->values()->toArray());
                        if (data.length === 0) return;

                        const colorMap = {
                            success: 'var(--nx-success)',
                            info: 'var(--nx-info)',
                            warning: 'var(--nx-warning)',
                            muted: 'var(--nx-muted)',
                            danger: 'var(--nx-danger)',
                            primary: 'color:var(--nx-accent)',
                        };

                        this.chart = new ApexCharts(this.$refs.chart, {
                            chart: { type: 'donut', height: 280 },
                            series: data.map(d => d.count),
                            labels: data.map(d => d.label),
                            colors: data.map(d => colorMap[d.color] || 'var(--nx-muted)'),
                            legend: { position: 'bottom', fontSize: '12px' },
                            dataLabels: { enabled: true, formatter: (val, opts) => opts.w.config.series[opts.seriesIndex] },
                            plotOptions: { pie: { donut: { size: '55%' } } },
                        });
                        this.chart.render();
                    },
                    destroy() { this.chart?.destroy(); }
                }" x-ref="chart" class="min-h-[280px]"></div>
            </div>

            {{-- Kategorie-Verteilung Donut --}}
            <div class="bg-[color:var(--nx-surface)] rounded-2xl shadow-[var(--nx-shadow-card)] p-6" wire:ignore>
                <h3 class="text-sm font-semibold text-[var(--nx-text)] mb-4">Kategorie-Verteilung</h3>
                <div x-data="{
                    chart: null,
                    init() {
                        const data = @js(collect($this->categoryCounts)->filter(fn($c) => $c['count'] > 0)->values()->toArray());
                        if (data.length === 0) return;

                        const colorMap = {
                            primary: 'color:var(--nx-accent)',
                            secondary: 'var(--nx-text)',
                            info: 'var(--nx-info)',
                            muted: 'var(--nx-muted)',
                        };

                        this.chart = new ApexCharts(this.$refs.chart, {
                            chart: { type: 'donut', height: 280 },
                            series: data.map(d => d.count),
                            labels: data.map(d => d.label),
                            colors: data.map(d => colorMap[d.color] || 'var(--nx-muted)'),
                            legend: { position: 'bottom', fontSize: '12px' },
                            dataLabels: { enabled: true, formatter: (val, opts) => opts.w.config.series[opts.seriesIndex] },
                            plotOptions: { pie: { donut: { size: '55%' } } },
                        });
                        this.chart.render();
                    },
                    destroy() { this.chart?.destroy(); }
                }" x-ref="chart" class="min-h-[280px]"></div>
            </div>
        </div>

        {{-- Status Pipeline + Focus --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            {{-- Status-Pipeline Bar --}}
            <div class="bg-[color:var(--nx-surface)] rounded-2xl shadow-[var(--nx-shadow-card)] p-6" wire:ignore>
                <h3 class="text-sm font-semibold text-[var(--nx-text)] mb-4">Status-Pipeline</h3>
                <div x-data="{
                    chart: null,
                    init() {
                        const data = @js(collect($this->statusCounts)->toArray());
                        const colorMap = {
                            muted: 'var(--nx-muted)',
                            warning: 'var(--nx-warning)',
                            info: 'var(--nx-info)',
                            success: 'var(--nx-success)',
                            danger: 'var(--nx-danger)',
                        };

                        this.chart = new ApexCharts(this.$refs.chart, {
                            chart: { type: 'bar', height: 250 },
                            series: [{ name: 'Prozesse', data: data.map(d => d.count) }],
                            xaxis: { categories: data.map(d => d.label) },
                            colors: data.map(d => colorMap[d.color] || 'var(--nx-muted)'),
                            plotOptions: {
                                bar: {
                                    distributed: true,
                                    borderRadius: 6,
                                    columnWidth: '60%',
                                }
                            },
                            dataLabels: { enabled: true, style: { fontSize: '13px', fontWeight: 600 } },
                            legend: { show: false },
                            grid: { borderColor: 'var(--nx-line)' },
                        });
                        this.chart.render();
                    },
                    destroy() { this.chart?.destroy(); }
                }" x-ref="chart" class="min-h-[250px]"></div>
            </div>

            {{-- Fokus-Prozesse --}}
            <x-nx-section title="Fokus-Prozesse" hint="Aktuell priorisiert">
                <x-nx-card flush class="p-4">
                <div class="space-y-2">
                    @forelse($this->focusProcesses as $process)
                        <a href="{{ route('process.processes.show', $process) }}" wire:navigate
                           class="group flex items-center justify-between p-3 rounded-lg border border-[color:var(--nx-line)] bg-[var(--nx-surface)] hover:border-[var(--nx-accent)]/60 hover:bg-[var(--nx-accent)]/8 transition-colors">
                            <div class="flex items-center gap-3 min-w-0">
                                @svg('heroicon-s-star', 'w-4 h-4 text-[color:var(--nx-warning)] flex-shrink-0')
                                <div class="min-w-0">
                                    <div class="font-medium text-[var(--nx-text)] truncate text-sm">{{ $process->name }}</div>
                                    @if($process->focus_reason)
                                        <div class="text-xs text-[var(--nx-muted)] truncate">{{ $process->focus_reason }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                @if($process->focus_until)
                                    <span class="text-[10px] text-[var(--nx-muted)]">bis {{ $process->focus_until->format('d.m.Y') }}</span>
                                @endif
                                <x-nx-badge :variant="$process->status->color()" size="sm">{{ $process->status->label() }}</x-nx-badge>
                            </div>
                        </a>
                    @empty
                        <div class="text-sm text-[var(--nx-muted)] p-4 text-center">Keine Fokus-Prozesse definiert.</div>
                    @endforelse
                </div>
            </x-nx-card>
            </x-nx-section>
        </div>

        {{-- Active Runs + Recent --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Aktive Runs --}}
            <x-nx-section title="Aktive Runs" hint="Laufende Prozessdurchläufe">
                <x-nx-card flush class="p-4">
                <div class="space-y-2">
                    @forelse($this->activeRuns as $run)
                        <a href="{{ route('process.processes.runs.show', [$run->process_id, $run->id]) }}" wire:navigate
                           class="group flex items-center justify-between p-3 rounded-lg border border-[color:var(--nx-line)] bg-[var(--nx-surface)] hover:border-[var(--nx-accent)]/60 hover:bg-[var(--nx-accent)]/8 transition-colors">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-2 h-2 rounded-full bg-[color:var(--nx-success)] animate-pulse flex-shrink-0"></div>
                                <div class="min-w-0">
                                    <div class="font-medium text-[var(--nx-text)] truncate text-sm">{{ $run->process?->name ?? 'Prozess' }}</div>
                                    <div class="text-xs text-[var(--nx-muted)]">
                                        Gestartet {{ $run->started_at?->diffForHumans() ?? '–' }}
                                    </div>
                                </div>
                            </div>
                            <x-nx-badge variant="success" size="sm">Aktiv</x-nx-badge>
                        </a>
                    @empty
                        <div class="text-sm text-[var(--nx-muted)] p-4 text-center">Keine aktiven Runs.</div>
                    @endforelse
                </div>
            </x-nx-card>
            </x-nx-section>

            {{-- Kürzlich geändert --}}
            <x-nx-section title="Kürzlich geändert" hint="Letzte Änderungen">
                <x-nx-card flush class="p-4">
                <div class="space-y-2">
                    @forelse($this->recentProcesses as $process)
                        <a href="{{ route('process.processes.show', $process) }}" wire:navigate
                           class="group flex items-center justify-between p-3 rounded-lg border border-[color:var(--nx-line)] bg-[var(--nx-surface)] hover:border-[var(--nx-accent)]/60 hover:bg-[var(--nx-accent)]/8 transition-colors">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-2 h-2 rounded-full flex-shrink-0 bg-[color:var(--nx-{{ $process->status->color() }})]"></div>
                                <div class="min-w-0">
                                    <div class="font-medium text-[var(--nx-text)] truncate text-sm">{{ $process->name }}</div>
                                    <div class="text-xs text-[var(--nx-muted)]">{{ $process->status->label() }}</div>
                                </div>
                            </div>
                            <span class="text-[10px] text-[var(--nx-muted)] flex-shrink-0">{{ $process->updated_at->diffForHumans() }}</span>
                        </a>
                    @empty
                        <div class="text-sm text-[var(--nx-muted)] p-4 text-center">Keine Prozesse vorhanden.</div>
                    @endforelse
                </div>
            </x-nx-card>
            </x-nx-section>
        </div>
    </x-ui-page-container>
</x-ui-page>
