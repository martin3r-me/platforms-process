<?php

namespace Platform\Process\Livewire;

use Livewire\Component;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\EntityDimensionBridge;
use Platform\Process\Models\Process;
use Platform\Process\Models\ProcessChain;

class Sidebar extends Component
{
    public function render()
    {
        $user = auth()->user();
        $teamId = $user?->currentTeam->id ?? null;

        if (!$user || !$teamId) {
            return view('process::livewire.sidebar', [
                'entityTypeGroups' => collect(),
                'unlinkedProcesses' => collect(),
                'unlinkedChains' => collect(),
            ]);
        }

        // 1. Load processes and chains for this team
        $processes = Process::where('team_id', $teamId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $chains = ProcessChain::where('team_id', $teamId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // 2. Get entity links for both types
        $processIds = $processes->pluck('id')->toArray();
        $chainIds = $chains->pluck('id')->toArray();

        $entityItemMap = []; // entity_id => ['processes' => [...], 'chains' => [...]]
        $linkedProcessIds = [];
        $linkedChainIds = [];

        try {
            // Processes linked to entities
            if (!empty($processIds)) {
                $processLinks = EntityDimensionBridge::linksForLinkables(
                    ['organization_process', Process::class],
                    $processIds
                );
                foreach ($processLinks as $link) {
                    $entityItemMap[$link->entity_id]['processes'][] = $link->linkable_id;
                    $linkedProcessIds[] = $link->linkable_id;
                }
            }

            // Chains linked to entities
            if (!empty($chainIds)) {
                $chainLinks = EntityDimensionBridge::linksForLinkables(
                    ['organization_process_chain', ProcessChain::class],
                    $chainIds
                );
                foreach ($chainLinks as $link) {
                    $entityItemMap[$link->entity_id]['chains'][] = $link->linkable_id;
                    $linkedChainIds[] = $link->linkable_id;
                }
            }
        } catch (\Throwable $e) {
            // Organization module not loaded
        }

        $linkedProcessIds = array_unique($linkedProcessIds);
        $linkedChainIds = array_unique($linkedChainIds);

        // 3. Ancestor traversal for tree display
        $directEntityIds = array_keys($entityItemMap);
        if (!empty($directEntityIds)) {
            $directEntities = OrganizationEntity::with(['allParents.type'])
                ->whereIn('id', $directEntityIds)
                ->get()
                ->keyBy('id');

            foreach ($directEntities as $entityId => $entity) {
                $ancestor = $entity->allParents;
                while ($ancestor) {
                    if (!isset($entityItemMap[$ancestor->id])) {
                        $entityItemMap[$ancestor->id] = [];
                    }
                    $ancestor = $ancestor->allParents;
                }
            }
        }

        // 4. Build entity type groups (tree structure)
        $entityTypeGroups = collect();
        $entityIds = array_keys($entityItemMap);

        if (!empty($entityIds)) {
            $entities = OrganizationEntity::with('type')
                ->whereIn('id', $entityIds)
                ->get()
                ->keyBy('id');

            // Parent-child relationships
            $entityChildrenMap = [];
            $rootEntityIds = [];

            foreach ($entities as $entity) {
                $parentId = $entity->parent_entity_id;
                if ($parentId && $entities->has($parentId)) {
                    $entityChildrenMap[$parentId][] = $entity->id;
                } else {
                    $rootEntityIds[] = $entity->id;
                }
            }

            // Recursive tree builder
            $buildTree = function (int $entityId) use (&$buildTree, $entities, $entityChildrenMap, $entityItemMap, $processes, $chains): ?array {
                $entity = $entities->get($entityId);
                if (!$entity) {
                    return null;
                }

                $childIds = $entityChildrenMap[$entityId] ?? [];
                $childNodes = collect($childIds)
                    ->map(fn ($childId) => $buildTree($childId))
                    ->filter();

                // Children grouped by type
                $childrenByType = $childNodes
                    ->groupBy(fn ($child) => $child['type_id'])
                    ->map(function ($group) use ($entities) {
                        $firstChild = $group->first();
                        $typeEntity = $entities->get($firstChild['entity_id']);
                        $type = $typeEntity?->type;

                        return [
                            'type_id' => $firstChild['type_id'],
                            'type_name' => $type?->name ?? 'Sonstige',
                            'type_icon' => $type?->icon ?? null,
                            'sort_order' => $type?->sort_order ?? 999,
                            'children' => $group->sortBy('entity_name')->values(),
                        ];
                    })
                    ->sortBy('sort_order')
                    ->values();

                $itemData = $entityItemMap[$entityId] ?? [];

                $entityProcesses = collect($itemData['processes'] ?? [])
                    ->map(fn ($id) => $processes->firstWhere('id', $id))
                    ->filter()
                    ->values();

                $entityChains = collect($itemData['chains'] ?? [])
                    ->map(fn ($id) => $chains->firstWhere('id', $id))
                    ->filter()
                    ->values();

                // Total items count (own + children)
                $totalItems = $entityProcesses->count() + $entityChains->count();
                foreach ($childNodes as $child) {
                    $totalItems += $child['total_items'];
                }

                if ($totalItems === 0) {
                    return null;
                }

                return [
                    'entity_id' => $entityId,
                    'entity_name' => $entity->name,
                    'type_id' => $entity->type?->id,
                    'processes' => $entityProcesses,
                    'chains' => $entityChains,
                    'children_by_type' => $childrenByType,
                    'total_items' => $totalItems,
                ];
            };

            // Root entities grouped by type
            $groupedByType = [];
            foreach ($rootEntityIds as $entityId) {
                $entity = $entities->get($entityId);
                if (!$entity || !$entity->type) {
                    continue;
                }

                $tree = $buildTree($entityId);
                if (!$tree) {
                    continue;
                }

                $typeId = $entity->type->id;
                if (!isset($groupedByType[$typeId])) {
                    $groupedByType[$typeId] = [
                        'type_id' => $typeId,
                        'type_name' => $entity->type->name,
                        'type_icon' => $entity->type->icon,
                        'sort_order' => $entity->type->sort_order ?? 999,
                        'entities' => [],
                    ];
                }
                $groupedByType[$typeId]['entities'][] = $tree;
            }

            $entityTypeGroups = collect($groupedByType)
                ->sortBy('sort_order')
                ->map(function ($group) {
                    $group['entities'] = collect($group['entities'])
                        ->sortBy('entity_name')
                        ->values();
                    return $group;
                })
                ->values();
        }

        // 5. Unlinked processes and chains
        $unlinkedProcesses = $processes->filter(fn ($p) => !in_array($p->id, $linkedProcessIds))->values();
        $unlinkedChains = $chains->filter(fn ($c) => !in_array($c->id, $linkedChainIds))->values();

        return view('process::livewire.sidebar', [
            'entityTypeGroups' => $entityTypeGroups,
            'unlinkedProcesses' => $unlinkedProcesses,
            'unlinkedChains' => $unlinkedChains,
        ]);
    }
}
