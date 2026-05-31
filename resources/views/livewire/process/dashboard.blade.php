<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[['label' => 'Prozesse']]">
            <x-slot name="left">
                <a href="{{ route('process.processes.list') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors">
                    @svg('heroicon-o-list-bullet', 'w-4 h-4')
                    <span>Listenansicht</span>
                </a>
            </x-slot>

            <x-ui-button variant="primary" size="sm" href="{{ route('process.processes.list') }}" wire:navigate>
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neuer Prozess</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        {{-- KPI Stat Tiles --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <x-ui-dashboard-tile
                title="Gesamt"
                :count="$this->totalProcesses"
                icon="arrow-path"
                variant="primary"
                size="lg"
                :href="route('process.processes.list')"
            />
            @php
                $activeCount = collect($this->statusCounts)->firstWhere('status', \Platform\Process\Enums\ProcessStatus::ACTIVE)['count'] ?? 0;
                $draftCount = collect($this->statusCounts)->firstWhere('status', \Platform\Process\Enums\ProcessStatus::DRAFT)['count'] ?? 0;
            @endphp
            <x-ui-dashboard-tile
                title="Aktiv"
                :count="$activeCount"
                icon="check-circle"
                variant="success"
                size="lg"
                :href="route('process.processes.index.status', 'active')"
            />
            <x-ui-dashboard-tile
                title="Entwurf"
                :count="$draftCount"
                icon="pencil-square"
                variant="muted"
                size="lg"
                :href="route('process.processes.index.status', 'draft')"
            />
            <x-ui-dashboard-tile
                title="Automatisierung"
                :count="$this->automationScore"
                icon="cpu-chip"
                variant="info"
                size="lg"
                description="Ø LLM-Score %"
            />
        </div>

        {{-- Charts Row --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            {{-- Status-Verteilung Donut --}}
            <div class="bg-white rounded-2xl shadow-sm p-6" wire:ignore>
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-4">Status-Verteilung</h3>
                <div x-data="{
                    chart: null,
                    init() {
                        const data = @js(collect($this->statusCounts)->filter(fn($s) => $s['count'] > 0)->values()->toArray());
                        if (data.length === 0) return;

                        const colorMap = {
                            success: 'rgb(var(--ui-success-rgb))',
                            info: 'rgb(var(--ui-info-rgb))',
                            warning: 'rgb(var(--ui-warning-rgb))',
                            muted: 'rgb(var(--ui-muted-rgb, 156 163 175))',
                            danger: 'rgb(var(--ui-danger-rgb))',
                            primary: 'rgb(var(--ui-primary-rgb))',
                        };

                        this.chart = new ApexCharts(this.$refs.chart, {
                            chart: { type: 'donut', height: 280 },
                            series: data.map(d => d.count),
                            labels: data.map(d => d.label),
                            colors: data.map(d => colorMap[d.color] || '#6b7280'),
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
            <div class="bg-white rounded-2xl shadow-sm p-6" wire:ignore>
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-4">Kategorie-Verteilung</h3>
                <div x-data="{
                    chart: null,
                    init() {
                        const data = @js(collect($this->categoryCounts)->filter(fn($c) => $c['count'] > 0)->values()->toArray());
                        if (data.length === 0) return;

                        const colorMap = {
                            primary: 'rgb(var(--ui-primary-rgb))',
                            secondary: 'rgb(var(--ui-secondary-rgb, 107 114 128))',
                            info: 'rgb(var(--ui-info-rgb))',
                            muted: 'rgb(var(--ui-muted-rgb, 156 163 175))',
                        };

                        this.chart = new ApexCharts(this.$refs.chart, {
                            chart: { type: 'donut', height: 280 },
                            series: data.map(d => d.count),
                            labels: data.map(d => d.label),
                            colors: data.map(d => colorMap[d.color] || '#6b7280'),
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
            <div class="bg-white rounded-2xl shadow-sm p-6" wire:ignore>
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-4">Status-Pipeline</h3>
                <div x-data="{
                    chart: null,
                    init() {
                        const data = @js(collect($this->statusCounts)->toArray());
                        const colorMap = {
                            muted: 'rgb(var(--ui-muted-rgb, 156 163 175))',
                            warning: 'rgb(var(--ui-warning-rgb))',
                            info: 'rgb(var(--ui-info-rgb))',
                            success: 'rgb(var(--ui-success-rgb))',
                            danger: 'rgb(var(--ui-danger-rgb))',
                        };

                        this.chart = new ApexCharts(this.$refs.chart, {
                            chart: { type: 'bar', height: 250 },
                            series: [{ name: 'Prozesse', data: data.map(d => d.count) }],
                            xaxis: { categories: data.map(d => d.label) },
                            colors: data.map(d => colorMap[d.color] || '#6b7280'),
                            plotOptions: {
                                bar: {
                                    distributed: true,
                                    borderRadius: 6,
                                    columnWidth: '60%',
                                }
                            },
                            dataLabels: { enabled: true, style: { fontSize: '13px', fontWeight: 600 } },
                            legend: { show: false },
                            grid: { borderColor: '#f3f4f6' },
                        });
                        this.chart.render();
                    },
                    destroy() { this.chart?.destroy(); }
                }" x-ref="chart" class="min-h-[250px]"></div>
            </div>

            {{-- Fokus-Prozesse --}}
            <x-ui-panel title="Fokus-Prozesse" subtitle="Aktuell priorisiert">
                <div class="space-y-2">
                    @forelse($this->focusProcesses as $process)
                        <a href="{{ route('process.processes.show', $process) }}" wire:navigate
                           class="group flex items-center justify-between p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)] transition-colors">
                            <div class="flex items-center gap-3 min-w-0">
                                @svg('heroicon-s-star', 'w-4 h-4 text-amber-400 flex-shrink-0')
                                <div class="min-w-0">
                                    <div class="font-medium text-[var(--ui-secondary)] truncate text-sm">{{ $process->name }}</div>
                                    @if($process->focus_reason)
                                        <div class="text-xs text-[var(--ui-muted)] truncate">{{ $process->focus_reason }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                @if($process->focus_until)
                                    <span class="text-[10px] text-[var(--ui-muted)]">bis {{ $process->focus_until->format('d.m.Y') }}</span>
                                @endif
                                <x-ui-badge :variant="$process->status->color()" size="sm">{{ $process->status->label() }}</x-ui-badge>
                            </div>
                        </a>
                    @empty
                        <div class="text-sm text-[var(--ui-muted)] p-4 text-center">Keine Fokus-Prozesse definiert.</div>
                    @endforelse
                </div>
            </x-ui-panel>
        </div>

        {{-- Active Runs + Recent --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Aktive Runs --}}
            <x-ui-panel title="Aktive Runs" subtitle="Laufende Prozessdurchläufe">
                <div class="space-y-2">
                    @forelse($this->activeRuns as $run)
                        <a href="{{ route('process.processes.runs.show', [$run->process_id, $run->id]) }}" wire:navigate
                           class="group flex items-center justify-between p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)] transition-colors">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse flex-shrink-0"></div>
                                <div class="min-w-0">
                                    <div class="font-medium text-[var(--ui-secondary)] truncate text-sm">{{ $run->process?->name ?? 'Prozess' }}</div>
                                    <div class="text-xs text-[var(--ui-muted)]">
                                        Gestartet {{ $run->started_at?->diffForHumans() ?? '–' }}
                                    </div>
                                </div>
                            </div>
                            <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                        </a>
                    @empty
                        <div class="text-sm text-[var(--ui-muted)] p-4 text-center">Keine aktiven Runs.</div>
                    @endforelse
                </div>
            </x-ui-panel>

            {{-- Kürzlich geändert --}}
            <x-ui-panel title="Kürzlich geändert" subtitle="Letzte Änderungen">
                <div class="space-y-2">
                    @forelse($this->recentProcesses as $process)
                        <a href="{{ route('process.processes.show', $process) }}" wire:navigate
                           class="group flex items-center justify-between p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)] transition-colors">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-2 h-2 rounded-full flex-shrink-0 bg-[rgb(var(--ui-{{ $process->status->color() }}-rgb))]"></div>
                                <div class="min-w-0">
                                    <div class="font-medium text-[var(--ui-secondary)] truncate text-sm">{{ $process->name }}</div>
                                    <div class="text-xs text-[var(--ui-muted)]">{{ $process->status->label() }}</div>
                                </div>
                            </div>
                            <span class="text-[10px] text-[var(--ui-muted)] flex-shrink-0">{{ $process->updated_at->diffForHumans() }}</span>
                        </a>
                    @empty
                        <div class="text-sm text-[var(--ui-muted)] p-4 text-center">Keine Prozesse vorhanden.</div>
                    @endforelse
                </div>
            </x-ui-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
