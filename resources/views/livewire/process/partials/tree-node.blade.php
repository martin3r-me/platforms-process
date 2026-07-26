{{-- Entity group header --}}
<div wire:key="node-{{ $node['entity']?->id ?? 'unowned' }}-{{ $node['depth'] }}" style="padding-left: {{ $node['depth'] * 24 }}px;">
    <div class="flex items-center gap-2 py-2 px-3 {{ $node['depth'] === 0 ? 'bg-[var(--nx-bg)] border border-[color:var(--nx-line)] rounded-lg mt-2' : '' }}">
        @if($node['depth'] === 0)
            @svg('heroicon-o-building-office-2', 'w-4 h-4 text-[var(--nx-muted)] flex-shrink-0')
        @else
            @svg('heroicon-o-folder', 'w-3.5 h-3.5 text-[var(--nx-muted)] flex-shrink-0')
        @endif
        <span class="text-sm font-semibold text-[var(--nx-text)]">{{ $node['label'] }}</span>
        @if($node['entity_type'])
            <span class="text-[10px] text-[var(--nx-muted)] bg-[var(--nx-line)] px-1.5 py-0.5 rounded">{{ $node['entity_type'] }}</span>
        @endif
        @php
            // Count all processes in this node + children recursively
            $countAll = count($node['processes']);
            $stack = $node['children'];
            while (!empty($stack)) {
                $child = array_shift($stack);
                $countAll += count($child['processes']);
                $stack = array_merge($stack, $child['children']);
            }
        @endphp
        <span class="text-[10px] text-[var(--nx-muted)]">{{ $countAll }} {{ $countAll === 1 ? 'Prozess' : 'Prozesse' }}</span>
    </div>
</div>

{{-- Direct processes of this entity --}}
@foreach($node['processes'] as $process)
    <div wire:key="process-{{ $process->id }}" style="padding-left: {{ ($node['depth'] + 1) * 24 }}px;">
        <div class="flex items-center gap-3 py-2 px-3 hover:bg-[var(--nx-bg)] rounded transition-colors group">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    @svg('heroicon-o-arrow-right-circle', 'w-3.5 h-3.5 text-[var(--nx-muted)] flex-shrink-0')
                    <a href="{{ route('process.processes.show', $process) }}" class="text-sm font-medium text-[var(--nx-accent)] hover:underline truncate" wire:navigate>
                        {{ $process->name }}
                    </a>
                    @if($process->code)
                        <code class="text-[10px] text-[var(--nx-muted)] flex-shrink-0">{{ $process->code }}</code>
                    @endif
                    @if($process->is_focus)
                        <span class="text-[color:var(--nx-warning)]" title="{{ $process->focus_reason ?? 'Fokus-Prozess' }}">
                            @svg('heroicon-s-star', 'w-4 h-4')
                        </span>
                    @endif
                    <x-nx-badge variant="{{ $process->status?->color() ?? 'muted' }}" size="sm">{{ $process->status?->label() ?? $process->status }}</x-nx-badge>
                    @if($process->process_category)
                        <x-nx-badge variant="{{ $process->process_category->color() }}" size="sm">
                            @svg($process->process_category->icon(), 'w-3 h-3')
                            {{ $process->process_category->label() }}
                        </x-nx-badge>
                    @endif
                </div>
                @if($process->description)
                    <div class="text-xs text-[var(--nx-muted)] ml-5.5 truncate">{{ \Illuminate\Support\Str::limit($process->description, 80) }}</div>
                @endif
            </div>

            {{-- Active Runs --}}
            @if(($process->active_runs_count ?? 0) > 0)
                <a href="{{ route('process.processes.show', $process) }}?activeTab=runs" class="flex items-center gap-1 flex-shrink-0 px-1.5 py-0.5 rounded-full bg-[var(--nx-warning)]/10 text-[var(--nx-warning)] hover:bg-[var(--nx-warning)]/20 transition-colors" wire:navigate title="Aktive Durchläufe">
                    @svg('heroicon-o-play', 'w-3 h-3')
                    <span class="text-[10px] font-bold">{{ $process->active_runs_count }}</span>
                </a>
            @endif

            {{-- Steps count --}}
            <span class="text-xs text-[var(--nx-muted)] flex-shrink-0 w-14 text-right">{{ $process->steps_count }} Steps</span>

            {{-- LLM Quote --}}
            @php
                $totalSteps = $process->steps->count();
                $llmSteps = $process->steps->filter(fn ($s) => $s->automation_level?->isLlm())->count();
                $llmQuote = $totalSteps > 0 ? round(($llmSteps / $totalSteps) * 100) : 0;
            @endphp
            <div class="flex items-center gap-1.5 flex-shrink-0 w-20">
                @if($totalSteps > 0)
                    <div class="w-12 bg-[color:var(--nx-line)] rounded-full h-1.5">
                        <div class="h-1.5 rounded-full {{ $llmQuote >= 70 ? 'bg-[var(--nx-success)]' : ($llmQuote >= 30 ? 'bg-[var(--nx-info)]' : 'bg-[var(--nx-muted)]') }}" style="width: {{ $llmQuote }}%"></div>
                    </div>
                    <span class="text-[10px] font-medium text-[var(--nx-text)]">{{ $llmQuote }}%</span>
                @else
                    <span class="text-[10px] text-[var(--nx-muted)]">–</span>
                @endif
            </div>

            {{-- Actions --}}
            <div class="flex gap-1 flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                <x-nx-button variant="secondary" icon size="sm" wire:click="edit({{ $process->id }})">
                    @svg('heroicon-o-pencil-square', 'w-3.5 h-3.5')
                </x-nx-button>
                <x-nx-button variant="danger" icon size="sm" wire:click="delete({{ $process->id }})" wire:confirm="Prozess wirklich löschen?">@svg('heroicon-o-trash', 'w-3.5 h-3.5')</x-nx-button>
            </div>
        </div>
    </div>
@endforeach

{{-- Recursive children --}}
@foreach($node['children'] as $childNode)
    @include('process::livewire.process.partials.tree-node', ['node' => $childNode])
@endforeach
