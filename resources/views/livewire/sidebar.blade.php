<div>
    <div x-show="!collapsed" class="px-3 pt-3 pb-2 border-b border-[#2C3135] mb-2">
        <span class="text-[10px] uppercase tracking-widest text-gray-500 font-medium">Prozesse</span>
    </div>

    <div x-show="!collapsed" class="px-2 mb-1">
        <a href="{{ route('process.processes.index') }}" wire:navigate class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
            @svg('heroicon-o-arrow-path', 'w-4 h-4')
            <span>Prozesse</span>
        </a>
    </div>

    {{-- Collapsed View --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[#2C3135]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('process.processes.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-[#2C3135] transition-colors" title="Prozesse">
                @svg('heroicon-o-arrow-path', 'w-5 h-5')
            </a>
        </div>
    </div>

    {{-- Entity-basierte Gruppierung --}}
    <div x-show="!collapsed" class="mt-2">
        @foreach($entityTypeGroups as $typeGroup)
            <x-ui-sidebar-list wire:key="type-group-{{ $typeGroup['type_id'] }}" :label="$typeGroup['type_name']">
                @foreach($typeGroup['entities'] as $entityNode)
                    @include('process::livewire.partials.sidebar-entity-node', [
                        'node' => $entityNode,
                        'typeIcon' => $typeGroup['type_icon'] ?? null,
                    ])
                @endforeach
            </x-ui-sidebar-list>
        @endforeach

        {{-- Unverknüpfte Prozesse --}}
        @if($unlinkedProcesses->isNotEmpty())
            <x-ui-sidebar-list label="Unverknüpft">
                @foreach($unlinkedProcesses as $process)
                    <a wire:key="unlinked-process-{{ $process->id }}"
                       href="{{ route('process.processes.show', $process) }}"
                       wire:navigate
                       title="{{ $process->name }}"
                       class="flex items-center gap-1.5 py-0.5 pl-3 pr-2 text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] transition truncate">
                        @svg('heroicon-o-arrow-path', 'w-3 h-3 flex-shrink-0 opacity-40')
                        <span class="truncate text-[11px]">{{ $process->name }}</span>
                    </a>
                @endforeach
            </x-ui-sidebar-list>
        @endif

        {{-- Unverknüpfte Ketten --}}
        @if($unlinkedChains->isNotEmpty())
            <x-ui-sidebar-list label="Ketten (unverknüpft)">
                @foreach($unlinkedChains as $chain)
                    <a wire:key="unlinked-chain-{{ $chain->id }}"
                       href="{{ route('process.processes.index') }}"
                       wire:navigate
                       title="{{ $chain->name }}"
                       class="flex items-center gap-1.5 py-0.5 pl-3 pr-2 text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] transition truncate">
                        @svg('heroicon-o-link', 'w-3 h-3 flex-shrink-0 opacity-40')
                        <span class="truncate text-[11px]">{{ $chain->name }}</span>
                    </a>
                @endforeach
            </x-ui-sidebar-list>
        @endif

        {{-- Leer-Zustand --}}
        @if($entityTypeGroups->isEmpty() && $unlinkedProcesses->isEmpty() && $unlinkedChains->isEmpty())
            <div class="px-3 py-1 text-xs text-[var(--ui-muted)]">
                Keine Prozesse oder Ketten
            </div>
        @endif
    </div>
</div>
