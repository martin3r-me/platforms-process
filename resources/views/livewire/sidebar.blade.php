<div>
    <div x-show="!collapsed" class="px-3 pt-3 pb-2 border-b border-[var(--nx-text)] mb-2">
        <span class="text-[10px] uppercase tracking-widest text-[color:var(--nx-muted)] font-medium">Prozesse</span>
    </div>

    <div x-show="!collapsed" class="px-2 mb-1">
        <a href="{{ route('process.processes.index') }}" wire:navigate class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-[color:var(--nx-faint)] hover:bg-[var(--nx-text)] hover:text-white transition-colors">
            @svg('heroicon-o-squares-2x2', 'w-4 h-4')
            <span>Dashboard</span>
        </a>
        <a href="{{ route('process.processes.list') }}" wire:navigate class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-[color:var(--nx-faint)] hover:bg-[var(--nx-text)] hover:text-white transition-colors">
            @svg('heroicon-o-arrow-path', 'w-4 h-4')
            <span>Listenansicht</span>
        </a>
    </div>

    {{-- Collapsed View --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--nx-text)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('process.processes.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[color:var(--nx-muted)] hover:text-white hover:bg-[var(--nx-text)] transition-colors" title="Dashboard">
                @svg('heroicon-o-squares-2x2', 'w-5 h-5')
            </a>
            <a href="{{ route('process.processes.list') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[color:var(--nx-muted)] hover:text-white hover:bg-[var(--nx-text)] transition-colors" title="Listenansicht">
                @svg('heroicon-o-arrow-path', 'w-5 h-5')
            </a>
        </div>
    </div>

    {{-- Status-basierte Gruppierung --}}
    <div x-show="!collapsed" class="mt-2">
        @foreach($statusGroups as $group)
            <div x-data="{ open: localStorage.getItem('process.status.{{ $group['status']->value }}') !== 'false' }"
                 wire:key="status-group-{{ $group['status']->value }}"
                 class="mb-1">
                {{-- Status-Header --}}
                <button type="button"
                        @click="open = !open; localStorage.setItem('process.status.{{ $group['status']->value }}', open)"
                        class="flex items-center gap-2 w-full px-3 py-1.5 text-left hover:bg-[var(--nx-text)] rounded-md transition-colors group">
                    <span class="w-3 h-3 flex-shrink-0 flex items-center justify-center transition-transform text-[var(--nx-muted)]"
                          :class="open ? 'rotate-90' : ''">
                        @svg('heroicon-o-chevron-right', 'w-2.5 h-2.5')
                    </span>
                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 bg-[color:var(--nx-{{ $group['color'] }})]"></span>
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-[color:var(--nx-muted)] group-hover:text-[color:var(--nx-faint)] transition-colors">
                        {{ $group['label'] }}
                    </span>
                    <span class="ml-auto text-[10px] tabular-nums text-[var(--nx-muted)] opacity-60">{{ $group['count'] }}</span>
                </button>

                {{-- Status-Inhalt --}}
                <div x-show="open" x-collapse class="ml-1">
                    {{-- Entity-Baum für diesen Status --}}
                    @foreach($group['linked'] as $typeGroup)
                        <x-ui-sidebar-list wire:key="status-{{ $group['status']->value }}-type-{{ $typeGroup['type_id'] }}" :label="$typeGroup['type_name']">
                            @foreach($typeGroup['entities'] as $entityNode)
                                @include('process::livewire.partials.sidebar-entity-node', [
                                    'node' => $entityNode,
                                    'typeIcon' => $typeGroup['type_icon'] ?? null,
                                ])
                            @endforeach
                        </x-ui-sidebar-list>
                    @endforeach

                    {{-- Unverknüpfte Prozesse dieses Status --}}
                    @if($group['unlinked']->isNotEmpty())
                        <x-ui-sidebar-list label="Unverknüpft">
                            @foreach($group['unlinked'] as $process)
                                <a wire:key="status-{{ $group['status']->value }}-unlinked-{{ $process->id }}"
                                   href="{{ route('process.processes.show', $process) }}"
                                   wire:navigate
                                   title="{{ $process->name }}"
                                   class="flex items-center gap-1.5 py-0.5 pl-3 pr-2 text-[var(--nx-text)] hover:text-[var(--nx-accent)] transition truncate">
                                    @svg('heroicon-o-arrow-path', 'w-3 h-3 flex-shrink-0 opacity-40')
                                    <span class="truncate text-[11px]">{{ $process->name }}</span>
                                </a>
                            @endforeach
                        </x-ui-sidebar-list>
                    @endif
                </div>
            </div>
        @endforeach

        {{-- Ketten --}}
        @if($chains->isNotEmpty())
            <x-ui-sidebar-list label="Ketten">
                @foreach($chains as $chain)
                    <a wire:key="chain-{{ $chain->id }}"
                       href="{{ route('process.processes.list') }}"
                       wire:navigate
                       title="{{ $chain->name }}"
                       class="flex items-center gap-1.5 py-0.5 pl-3 pr-2 text-[var(--nx-text)] hover:text-[var(--nx-accent)] transition truncate">
                        @svg('heroicon-o-link', 'w-3 h-3 flex-shrink-0 opacity-40')
                        <span class="truncate text-[11px]">{{ $chain->name }}</span>
                    </a>
                @endforeach
            </x-ui-sidebar-list>
        @endif

        {{-- Leer-Zustand --}}
        @if($statusGroups->isEmpty() && $chains->isEmpty())
            <div class="px-3 py-1 text-xs text-[var(--nx-muted)]">
                Keine Prozesse oder Ketten
            </div>
        @endif
    </div>
</div>
