<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="array_filter([
            ['label' => 'Prozesse', 'href' => $statusFromRoute ? route('process.processes.list') : null],
            $statusFromRoute ? ['label' => match($statusFilter) {
                'draft' => 'Entwurf',
                'under_review' => 'In Prüfung',
                'pilot' => 'Pilot',
                'active' => 'Aktiv',
                'deprecated' => 'Veraltet',
                default => $statusFilter,
            }] : null,
        ])">
            <x-slot name="left">
                <x-nx-input-select
                    wire:key="filter-status"
                    name="statusFilter"
                    :options="['draft' => 'Entwurf', 'under_review' => 'In Prüfung', 'pilot' => 'Pilot', 'active' => 'Aktiv', 'deprecated' => 'Veraltet']"
                    wire:model.live="statusFilter"
                    :nullable="true"
                    nullLabel="Alle Status"
                    size="xs"
                />
                <x-nx-input-select
                    wire:key="filter-category"
                    name="categoryFilter"
                    :options="\Platform\Process\Enums\ProcessCategory::cases()"
                    optionValue="value"
                    optionLabel="label"
                    wire:model.live="categoryFilter"
                    :nullable="true"
                    nullLabel="Alle Kategorien"
                    size="xs"
                />
                <button wire:key="filter-focus" wire:click="$toggle('focusFilter')" class="inline-flex items-center gap-1 text-xs px-3 py-1 rounded-md border transition-colors {{ $focusFilter ? 'bg-[var(--nx-warning)]/10 text-[color:var(--nx-warning)] border-[var(--nx-warning)]/30 font-medium' : 'text-[color:var(--nx-muted)] border-[color:var(--nx-line)] hover:bg-[color:var(--nx-bg)] hover:text-[color:var(--nx-text)]' }}">
                    @svg('heroicon-o-star', 'w-3.5 h-3.5')
                    Fokus
                </button>
            </x-slot>

            <x-nx-button variant="primary" size="sm" wire:click="create">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neuer Prozess</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--nx-text)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-nx-input-text name="search" wire:model.live="search" placeholder="Name, Code, Beschreibung..." class="w-full" size="sm" />
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 text-sm text-[var(--nx-muted)]">Keine Aktivitäten verfügbar</div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container width="contained">
        @php $tree = $this->processTree; @endphp

        @if(count($tree) === 0 && $this->processes->isEmpty())
            <div wire:key="empty-state" class="text-center text-[var(--nx-muted)] py-12">Keine Prozesse gefunden.</div>
        @else
            <div wire:key="process-tree" class="space-y-1">
                @foreach($tree as $node)
                    @include('process::livewire.process.partials.tree-node', ['node' => $node])
                @endforeach
            </div>
        @endif
    </x-ui-page-container>

    <!-- Create/Edit Modal -->
    <x-nx-modal wire:model="modalShow" size="lg" wire:key="process-modal-{{ $editingId ?? 'create' }}">
        <x-slot name="header">
            {{ $editingId ? 'Prozess bearbeiten' : 'Neuen Prozess erstellen' }}
        </x-slot>

        <form wire:submit.prevent="store" class="space-y-4">
            <x-nx-input-text name="name" label="Name" wire:model.live="form.name" required placeholder="z.B. Onboarding neuer Mitarbeiter" />

            <x-nx-input-text name="code" label="Code (optional)" wire:model.live="form.code" placeholder="z.B. PROC-001" />

            <x-nx-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" rows="3" />

            <div class="grid grid-cols-2 gap-4">
                <x-nx-input-select
                    name="form.status"
                    label="Status"
                    :options="['draft' => 'Entwurf', 'under_review' => 'In Prüfung', 'pilot' => 'Pilot', 'active' => 'Aktiv', 'deprecated' => 'Veraltet']"
                    wire:model.live="form.status"
                    :required="true"
                    size="sm"
                />
                <x-nx-input-select
                    name="form.process_category"
                    label="Kategorie"
                    :options="\Platform\Process\Enums\ProcessCategory::cases()"
                    optionValue="value"
                    optionLabel="label"
                    wire:model.live="form.process_category"
                    :nullable="true"
                    nullLabel="– Keine Kategorie –"
                    size="sm"
                />
            </div>

            <div class="space-y-3">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model.live="form.is_focus" class="rounded border-[color:var(--nx-line)] text-[color:var(--nx-warning)] focus:ring-[var(--nx-warning)]/30">
                    <span class="text-sm font-medium text-[color:var(--nx-text)]">Fokus-Prozess</span>
                </label>
                @if($form['is_focus'])
                    <x-nx-input-textarea name="focus_reason" label="Fokus-Begründung" wire:model.live="form.focus_reason" rows="2" placeholder="Warum ist dieser Prozess im Fokus?" />
                    <x-nx-input-text type="date" name="focus_until" label="Fokus bis" wire:model.live="form.focus_until" />
                @endif
            </div>

            <div class="grid grid-cols-2 gap-4">
                <x-nx-input-select
                    name="form.owner_entity_id"
                    label="Owner (Entity)"
                    :options="$this->availableEntities"
                    optionValue="id"
                    optionLabel="name"
                    wire:model.live="form.owner_entity_id"
                    :nullable="true"
                    nullLabel="– Kein Owner –"
                    size="sm"
                />
            </div>
        </form>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-nx-button type="button" variant="secondary" wire:click="$set('modalShow', false)">
                    Abbrechen
                </x-nx-button>
                <x-nx-button type="button" variant="primary" wire:click="store">
                    @svg('heroicon-o-check', 'w-4 h-4 mr-2')
                    {{ $editingId ? 'Speichern' : 'Erstellen' }}
                </x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
