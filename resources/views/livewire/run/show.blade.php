<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Prozesse', 'href' => route('process.processes.index')],
            ['label' => $process->name, 'href' => route('process.processes.show', $process)],
            ['label' => 'Durchlauf ' . $run->started_at->format('d.m.Y H:i')],
        ]">
            <div class="flex-1"></div>

            @if($this->isActive)
                <x-nx-button variant="danger" size="sm" wire:click="cancelRun" wire:confirm="Durchlauf wirklich abbrechen?">
                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                    <span>Abbrechen</span>
                </x-nx-button>
            @endif

            <x-nx-button variant="danger" icon size="sm" wire:click="deleteRun" wire:confirm="Durchlauf wirklich löschen?">@svg('heroicon-o-trash', 'w-4 h-4')</x-nx-button>

            <a href="{{ route('process.processes.show', $process) }}?tab=runs" wire:navigate>
                <x-nx-button variant="secondary" size="sm">
                    @svg('heroicon-o-arrow-left', 'w-4 h-4')
                    <span>Zurück</span>
                </x-nx-button>
            </a>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Informationen" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                {{-- Status --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--nx-text)] uppercase tracking-wider mb-3">Status</h3>
                    <div class="space-y-2">
                        <x-nx-badge variant="{{ $run->status->color() }}" size="sm">{{ $run->status->label() }}</x-nx-badge>
                    </div>
                </div>

                {{-- Fortschritt --}}
                @php $prog = $this->progress; @endphp
                <div>
                    <h3 class="text-sm font-bold text-[var(--nx-text)] uppercase tracking-wider mb-3">Fortschritt</h3>
                    <div class="py-3 px-4 bg-[var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs text-[var(--nx-muted)]">{{ $prog['done'] }} von {{ $prog['total'] }} Schritte</span>
                            <span class="text-lg font-bold text-[var(--nx-text)]">{{ $prog['percent'] }}%</span>
                        </div>
                        <div class="w-full bg-[color:var(--nx-line)] rounded-full h-2">
                            <div class="h-2 rounded-full bg-[var(--nx-{{ $run->status->color() }})] transition-all" style="width: {{ $prog['percent'] }}%"></div>
                        </div>
                    </div>
                </div>

                {{-- Zeiten --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--nx-text)] uppercase tracking-wider mb-3">Zeiten</h3>
                    <div class="py-3 px-4 bg-[var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)] space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-[var(--nx-muted)]">Aktive Zeit</span>
                            <span class="text-sm text-[var(--nx-text)]">{{ $this->totalActive }} Min.</span>
                        </div>
                        @if($this->targetActive > 0)
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-[var(--nx-muted)]">Soll (aktiv)</span>
                                <span class="text-sm text-[var(--nx-muted)]">{{ $this->targetActive }} Min.</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-[var(--nx-muted)]">Wartezeit</span>
                            <span class="text-sm text-[var(--nx-text)]">{{ $this->totalWait }} Min.</span>
                        </div>
                        @if($this->targetWait > 0)
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-[var(--nx-muted)]">Soll (warten)</span>
                                <span class="text-sm text-[var(--nx-muted)]">{{ $this->targetWait }} Min.</span>
                            </div>
                        @endif
                        @if($this->targetActive > 0)
                            @php $activeDelta = $this->totalActive - $this->targetActive; @endphp
                            <div class="pt-1 border-t border-[color:var(--nx-line)]">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-[var(--nx-muted)]">Abweichung</span>
                                    <span class="text-sm font-bold {{ $activeDelta > 0 ? 'text-[color:var(--nx-danger)]' : ($activeDelta < 0 ? 'text-[color:var(--nx-success)]' : 'text-[var(--nx-text)]') }}">
                                        {{ $activeDelta > 0 ? '+' : '' }}{{ $activeDelta }} Min.
                                    </span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Details --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--nx-text)] uppercase tracking-wider mb-3">Details</h3>
                    <div class="space-y-3">
                        <div class="py-3 px-4 bg-[var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                            <span class="text-xs text-[var(--nx-muted)]">Prozess</span>
                            <div class="text-sm font-medium text-[var(--nx-text)]">{{ $process->name }}</div>
                            @if($process->code)
                                <div class="text-xs text-[var(--nx-muted)]">{{ $process->code }}</div>
                            @endif
                        </div>
                        <div class="py-3 px-4 bg-[var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                            <span class="text-xs text-[var(--nx-muted)]">Gestartet</span>
                            <div class="text-sm font-medium text-[var(--nx-text)]">{{ $run->started_at->format('d.m.Y H:i') }}</div>
                        </div>
                        @if($run->completed_at)
                            <div class="py-3 px-4 bg-[var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                                <span class="text-xs text-[var(--nx-muted)]">Abgeschlossen</span>
                                <div class="text-sm font-medium text-[var(--nx-text)]">{{ $run->completed_at->format('d.m.Y H:i') }}</div>
                            </div>
                        @endif
                        @if($run->cancelled_at)
                            <div class="py-3 px-4 bg-[var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                                <span class="text-xs text-[var(--nx-muted)]">Abgebrochen</span>
                                <div class="text-sm font-medium text-[var(--nx-text)]">{{ $run->cancelled_at->format('d.m.Y H:i') }}</div>
                            </div>
                        @endif
                        @if($run->user)
                            <div class="py-3 px-4 bg-[var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                                <span class="text-xs text-[var(--nx-muted)]">Erstellt von</span>
                                <div class="text-sm font-medium text-[var(--nx-text)]">{{ $run->user->name }}</div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Notizen --}}
                @if($run->notes)
                    <div>
                        <h3 class="text-sm font-bold text-[var(--nx-text)] uppercase tracking-wider mb-3">Notizen</h3>
                        <div class="py-3 px-4 bg-[var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                            <p class="text-sm text-[var(--nx-muted)]">{{ $run->notes }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 text-sm text-[var(--nx-muted)]">Keine Aktivitäten verfügbar</div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container width="contained">
        @php
            $prog = $this->progress;
            $leadTime = $this->totalActive + $this->totalWait;
            $targetLead = $this->targetActive + $this->targetWait;
            $leadDelta = $targetLead > 0 ? round((($leadTime - $targetLead) / $targetLead) * 100, 1) : 0;
            $completedSteps = $this->runSteps->where('status', \Platform\Process\Enums\RunStepStatus::COMPLETED)->count();
            $skippedSteps = $this->runSteps->where('status', \Platform\Process\Enums\RunStepStatus::SKIPPED)->count();
            $pendingSteps = $this->runSteps->where('status', \Platform\Process\Enums\RunStepStatus::PENDING)->count();
        @endphp

        {{-- Mini-Dashboard --}}
        <div class="bg-[color:var(--nx-surface)] rounded-lg border border-[color:var(--nx-line)] p-6">
            <div class="flex items-start justify-between mb-5">
                <div>
                    <h1 class="text-xl font-bold text-[var(--nx-text)]">{{ $process->name }}</h1>
                    <div class="flex items-center gap-3 mt-1.5">
                        <x-nx-badge variant="{{ $run->status->color() }}" size="sm">{{ $run->status->label() }}</x-nx-badge>
                        <span class="text-xs text-[var(--nx-muted)]">{{ $run->started_at->format('d.m.Y H:i') }}</span>
                        @if($run->user)
                            <span class="text-xs text-[var(--nx-muted)]">von {{ $run->user->name }}</span>
                        @endif
                    </div>
                </div>
                @if($prog['percent'] > 0)
                    <div class="text-right">
                        <span class="text-3xl font-bold text-[var(--nx-{{ $run->status->color() }})]">{{ $prog['percent'] }}%</span>
                    </div>
                @endif
            </div>

            {{-- Progress Bar --}}
            <div class="mb-5">
                <div class="w-full bg-[color:var(--nx-line)] rounded-full h-2.5">
                    <div class="h-2.5 rounded-full bg-[var(--nx-{{ $run->status->color() }})] transition-all" style="width: {{ $prog['percent'] }}%"></div>
                </div>
            </div>

            {{-- Stats Grid --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="py-3 px-4 bg-[var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                    <div class="flex items-center gap-2 mb-1">
                        @svg('heroicon-o-check-circle', 'w-4 h-4 text-[var(--nx-success)]')
                        <span class="text-xs text-[var(--nx-muted)]">Erledigt</span>
                    </div>
                    <span class="text-lg font-bold text-[var(--nx-text)]">{{ $completedSteps }}</span>
                    <span class="text-xs text-[var(--nx-muted)]"> / {{ $prog['total'] }}</span>
                </div>
                <div class="py-3 px-4 bg-[var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                    <div class="flex items-center gap-2 mb-1">
                        @svg('heroicon-o-clock', 'w-4 h-4 text-[var(--nx-info)]')
                        <span class="text-xs text-[var(--nx-muted)]">Aktive Zeit</span>
                    </div>
                    <span class="text-lg font-bold text-[var(--nx-text)]">{{ $this->totalActive }} Min.</span>
                    @if($this->targetActive > 0)
                        <span class="text-[10px] text-[var(--nx-muted)] block">Soll: {{ $this->targetActive }} Min.</span>
                    @endif
                </div>
                <div class="py-3 px-4 bg-[var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                    <div class="flex items-center gap-2 mb-1">
                        @svg('heroicon-o-pause', 'w-4 h-4 text-[var(--nx-warning)]')
                        <span class="text-xs text-[var(--nx-muted)]">Wartezeit</span>
                    </div>
                    <span class="text-lg font-bold text-[var(--nx-text)]">{{ $this->totalWait }} Min.</span>
                    @if($this->targetWait > 0)
                        <span class="text-[10px] text-[var(--nx-muted)] block">Soll: {{ $this->targetWait }} Min.</span>
                    @endif
                </div>
                <div class="py-3 px-4 bg-[var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                    <div class="flex items-center gap-2 mb-1">
                        @svg('heroicon-o-arrow-trending-up', 'w-4 h-4 text-[var(--nx-muted)]')
                        <span class="text-xs text-[var(--nx-muted)]">Durchlaufzeit</span>
                    </div>
                    <span class="text-lg font-bold text-[var(--nx-text)]">{{ $leadTime }} Min.</span>
                    @if($targetLead > 0)
                        <span class="text-[10px] {{ $leadDelta > 0 ? 'text-[color:var(--nx-danger)]' : ($leadDelta < 0 ? 'text-[color:var(--nx-success)]' : 'text-[var(--nx-muted)]') }} block">
                            {{ $leadDelta > 0 ? '+' : '' }}{{ $leadDelta }}% vs. Soll
                        </span>
                    @endif
                </div>
            </div>

            @if($skippedSteps > 0 || $pendingSteps > 0)
                <div class="flex items-center gap-4 mt-3 pt-3 border-t border-[color:var(--nx-line)]">
                    @if($skippedSteps > 0)
                        <span class="text-xs text-[var(--nx-muted)] flex items-center gap-1">
                            @svg('heroicon-o-minus-circle', 'w-3.5 h-3.5')
                            {{ $skippedSteps }} übersprungen
                        </span>
                    @endif
                    @if($pendingSteps > 0)
                        <span class="text-xs text-[var(--nx-muted)] flex items-center gap-1">
                            @svg('heroicon-o-ellipsis-horizontal-circle', 'w-3.5 h-3.5')
                            {{ $pendingSteps }} offen
                        </span>
                    @endif
                </div>
            @endif
        </div>

        {{-- Step Checklist --}}
        <div class="space-y-3">
            @foreach($this->runSteps as $rs)
                @php
                    $isCompleted = $rs->status === \Platform\Process\Enums\RunStepStatus::COMPLETED;
                    $isSkipped = $rs->status === \Platform\Process\Enums\RunStepStatus::SKIPPED;
                    $isPending = $rs->status === \Platform\Process\Enums\RunStepStatus::PENDING;
                @endphp
                <div
                    x-data="{ editing: false, activeDur: '', waitDur: '' }"
                    class="bg-[color:var(--nx-surface)] rounded-xl border border-[color:var(--nx-line)] {{ $isCompleted ? 'bg-[var(--nx-success)]/10 border-[var(--nx-success)]/30' : ($isSkipped ? 'bg-[var(--nx-bg)]/50 border-[color:var(--nx-line)]' : '') }} {{ $isPending && $this->isActive ? 'hover:border-[var(--nx-info)] hover:shadow-[var(--nx-shadow-card)] cursor-pointer' : '' }} transition-all"
                    @if($isPending && $this->isActive) @click="editing = !editing" @endif
                >
                    <div class="flex items-start gap-4 px-6 py-5">
                        {{-- Status indicator --}}
                        <div class="flex-shrink-0 mt-0.5">
                            @if($isCompleted)
                                <div class="w-9 h-9 rounded-full bg-[var(--nx-success)] flex items-center justify-center shadow-[var(--nx-shadow-card)]">
                                    @svg('heroicon-s-check', 'w-5 h-5 text-white')
                                </div>
                            @elseif($isSkipped)
                                <div class="w-9 h-9 rounded-full bg-[var(--nx-muted)]/60 flex items-center justify-center">
                                    @svg('heroicon-s-minus', 'w-5 h-5 text-white')
                                </div>
                            @elseif($isPending && $this->isActive)
                                <div class="w-9 h-9 rounded-full border-2 border-[color:var(--nx-line)] hover:border-[var(--nx-success)] transition-colors flex items-center justify-center">
                                    <span class="text-xs font-bold text-[var(--nx-muted)]">{{ $rs->position }}</span>
                                </div>
                            @else
                                <div class="w-9 h-9 rounded-full border-2 border-[color:var(--nx-line)] flex items-center justify-center">
                                    <span class="text-xs text-[var(--nx-muted)]/50">{{ $rs->position }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- Content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-base font-medium {{ $isSkipped ? 'line-through text-[var(--nx-muted)]' : ($isCompleted ? 'text-[var(--nx-muted)]' : 'text-[var(--nx-text)]') }}">
                                        {{ $rs->processStep?->name ?? 'Step' }}
                                    </span>
                                    @if($isSkipped)
                                        <span class="text-xs text-[var(--nx-muted)] italic">Übersprungen</span>
                                    @endif
                                </div>
                                @if($rs->processStep?->duration_target_minutes)
                                    <span class="text-xs text-[var(--nx-muted)] flex-shrink-0 ml-2 flex items-center gap-1">
                                        @svg('heroicon-o-clock', 'w-3.5 h-3.5')
                                        Soll: {{ $rs->processStep->duration_target_minutes }} Min.
                                        @if($rs->processStep->wait_target_minutes)
                                            <span class="text-[var(--nx-muted)]/60">+ {{ $rs->processStep->wait_target_minutes }} Warten</span>
                                        @endif
                                    </span>
                                @endif
                            </div>

                            @if($rs->processStep?->description)
                                <p class="text-sm text-[var(--nx-muted)] mt-1">{{ $rs->processStep->description }}</p>
                            @endif

                            {{-- Completed/skipped details --}}
                            @if($isCompleted || $isSkipped)
                                <div class="flex flex-wrap gap-3 mt-2.5">
                                    @if($rs->active_duration_minutes !== null)
                                        <span class="text-xs text-[var(--nx-muted)] flex items-center gap-1.5 py-1 px-2.5 bg-[var(--nx-bg)] rounded-md">
                                            @svg('heroicon-o-clock', 'w-3.5 h-3.5')
                                            Aktiv: {{ $rs->active_duration_minutes }} Min.
                                        </span>
                                    @endif
                                    @if($rs->wait_duration_minutes !== null)
                                        <span class="text-xs text-[var(--nx-muted)] flex items-center gap-1.5 py-1 px-2.5 bg-[var(--nx-bg)] rounded-md">
                                            @svg('heroicon-o-pause', 'w-3.5 h-3.5')
                                            Wartezeit: {{ $rs->wait_duration_minutes }} Min.{{ $rs->wait_override ? ' (manuell)' : '' }}
                                        </span>
                                    @endif
                                    @if($rs->processStep?->duration_target_minutes && $rs->active_duration_minutes !== null)
                                        @php $delta = $rs->active_duration_minutes - $rs->processStep->duration_target_minutes; @endphp
                                        <span class="text-xs flex items-center gap-1.5 py-1 px-2.5 rounded-md {{ $delta > 0 ? 'text-[color:var(--nx-danger)] bg-[var(--nx-danger)]/10' : 'text-[color:var(--nx-success)] bg-[var(--nx-success)]/10' }}">
                                            @svg($delta > 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down', 'w-3.5 h-3.5')
                                            {{ $delta > 0 ? '+' : '' }}{{ $delta }} Min.
                                        </span>
                                    @endif
                                    @if($rs->checked_at)
                                        <span class="text-xs text-[var(--nx-muted)] flex items-center gap-1.5 py-1 px-2.5">
                                            {{ $rs->checked_at->format('H:i') }}
                                        </span>
                                    @endif
                                </div>
                            @endif

                            {{-- Inline edit for pending steps --}}
                            @if($isPending && $this->isActive)
                                <div x-show="editing" x-transition @click.stop class="mt-4 p-4 bg-[var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                                    <div class="flex items-end gap-3 flex-wrap">
                                        <div>
                                            <label class="text-xs font-medium text-[var(--nx-muted)] block mb-1.5">Aktive Zeit (Min.)</label>
                                            <input type="number" x-model="activeDur" min="0" placeholder="0" class="w-28 text-sm px-3 py-2 rounded-lg border border-[color:var(--nx-line)] focus:border-[var(--nx-info)] focus:ring-1 focus:ring-[var(--nx-info)] bg-[color:var(--nx-surface)]" />
                                        </div>
                                        <div>
                                            <label class="text-xs font-medium text-[var(--nx-muted)] block mb-1.5">Wartezeit (Min., opt.)</label>
                                            <input type="number" x-model="waitDur" min="0" placeholder="auto" class="w-28 text-sm px-3 py-2 rounded-lg border border-[color:var(--nx-line)] focus:border-[var(--nx-info)] focus:ring-1 focus:ring-[var(--nx-info)] bg-[color:var(--nx-surface)]" />
                                        </div>
                                        <button
                                            type="button"
                                            @click="$wire.completeStep({{ $rs->id }}, activeDur ? parseInt(activeDur) : null, waitDur ? parseInt(waitDur) : null); editing = false"
                                            class="px-5 py-2 text-sm font-medium bg-[var(--nx-success)] text-white rounded-lg hover:bg-[var(--nx-success)]/90 transition-colors flex items-center gap-2"
                                        >
                                            @svg('heroicon-o-check', 'w-4 h-4')
                                            Erledigt
                                        </button>
                                        <button
                                            type="button"
                                            @click="$wire.skipStep({{ $rs->id }}); editing = false"
                                            class="px-4 py-2 text-sm text-[var(--nx-muted)] hover:text-[var(--nx-text)] transition-colors"
                                        >
                                            Überspringen
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-ui-page-container>
</x-ui-page>
