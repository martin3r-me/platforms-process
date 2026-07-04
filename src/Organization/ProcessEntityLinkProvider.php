<?php

namespace Platform\Process\Organization;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Contracts\EntityLinkProvider;
use Platform\Organization\Contracts\HasMetricDefinitions;

class ProcessEntityLinkProvider implements EntityLinkProvider, HasMetricDefinitions
{
    public function morphAliases(): array
    {
        return ['organization_process'];
    }

    public function linkTypeConfig(): array
    {
        return [
            'organization_process' => [
                'label' => 'Prozesse',
                'singular' => 'Prozess',
                'icon' => 'bolt',
                'route' => 'process.processes.show',
            ],
        ];
    }

    public function applyEagerLoading(Builder $query, string $morphAlias, string $fqcn): void
    {
        $query->withCount('steps')
            ->withCount('runs')
            ->withCount(['runs as runs_active_count' => fn ($q) => $q->where('status', 'active')])
            ->withCount(['runs as runs_completed_count' => fn ($q) => $q->where('status', 'completed')]);
    }

    public function extractMetadata(string $morphAlias, mixed $model): array
    {
        return [
            'code' => $model->code,
            'status' => $model->status?->value ?? null,
            'is_active' => $model->is_active,
            'steps_count' => $model->steps_count ?? 0,
            'frequency' => $model->frequency?->value ?? null,
        ];
    }

    public function metadataDisplayRules(): array
    {
        return [
            'code' => ['type' => 'text', 'label' => 'Code'],
            'status' => ['type' => 'badge', 'label' => 'Status'],
            'is_active' => ['type' => 'boolean', 'label' => 'Aktiv'],
            'steps_count' => ['type' => 'number', 'label' => 'Schritte'],
            'frequency' => ['type' => 'text', 'label' => 'Frequenz'],
        ];
    }

    public function timeTrackableCascades(): array
    {
        return [];
    }

    public function metrics(string $morphAlias, array $linksByEntity): array
    {
        $allIds = [];
        foreach ($linksByEntity as $ids) {
            $allIds = array_merge($allIds, $ids);
        }
        $allIds = array_values(array_unique($allIds));

        if (empty($allIds)) {
            return [];
        }

        // Count active processes
        $activeIds = DB::table('processes')
            ->whereIn('id', $allIds)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->flip()
            ->all();

        // Focus-Prozesse: is_focus=true UND focus_until in Zukunft oder null.
        $focusIds = DB::table('processes')
            ->whereIn('id', $allIds)
            ->where('is_focus', true)
            ->where(function ($q) {
                $q->whereNull('focus_until')->orWhere('focus_until', '>=', now()->toDateString());
            })
            ->whereNull('deleted_at')
            ->pluck('id')
            ->flip()
            ->all();

        $window7d = now()->subDays(7);
        $window30d = now()->subDays(30);
        $stallCutoff = now()->subDays(7); // aktiv laenger als 7 Tage = stalled

        // Runs im 30-Tage-Fenster (bestehende Aggregation, jetzt korrekt window_30d).
        $runStats = DB::table('process_runs')
            ->whereIn('process_id', $allIds)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $window30d)
            ->select(
                'process_id',
                DB::raw("COUNT(*) as total_runs"),
                DB::raw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_runs"),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_runs"),
                DB::raw("SUM(CASE WHEN status = 'cancelled' AND cancelled_at IS NOT NULL AND cancelled_at >= '" . $window30d->toDateTimeString() . "' THEN 1 ELSE 0 END) as cancelled_runs_30d"),
                DB::raw("AVG(CASE WHEN status = 'completed' AND completed_at IS NOT NULL AND started_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, started_at, completed_at) END) as avg_cycle_minutes"),
            )
            ->groupBy('process_id')
            ->get()
            ->keyBy('process_id');

        // Zusaetzlich pro Prozess: gestartete Runs im 7d-Fenster und Stall-Kandidaten.
        $windowStats = DB::table('process_runs')
            ->whereIn('process_id', $allIds)
            ->whereNull('deleted_at')
            ->select(
                'process_id',
                DB::raw("SUM(CASE WHEN started_at >= '" . $window7d->toDateTimeString() . "' THEN 1 ELSE 0 END) as started_7d"),
                DB::raw("SUM(CASE WHEN status = 'active' AND started_at < '" . $stallCutoff->toDateTimeString() . "' THEN 1 ELSE 0 END) as stalled"),
            )
            ->groupBy('process_id')
            ->get()
            ->keyBy('process_id');

        $result = [];
        foreach ($linksByEntity as $entityId => $ids) {
            $total = count($ids);
            $active = 0;
            $focus = 0;
            $runsActive = 0;
            $runsCompleted = 0;
            $runsCancelled30d = 0;
            $runsStarted7d = 0;
            $runsStalled = 0;
            $cycleSums = [];

            foreach ($ids as $id) {
                if (isset($activeIds[$id])) {
                    $active++;
                }
                if (isset($focusIds[$id])) {
                    $focus++;
                }
                $stats = $runStats[$id] ?? null;
                if ($stats) {
                    $runsActive += (int) $stats->active_runs;
                    $runsCompleted += (int) $stats->completed_runs;
                    $runsCancelled30d += (int) $stats->cancelled_runs_30d;
                    if ($stats->avg_cycle_minutes !== null) {
                        $cycleSums[] = (float) $stats->avg_cycle_minutes;
                    }
                }
                $win = $windowStats[$id] ?? null;
                if ($win) {
                    $runsStarted7d += (int) $win->started_7d;
                    $runsStalled += (int) $win->stalled;
                }
            }

            $completionBase = $runsCompleted + $runsCancelled30d;
            $completionRate = $completionBase > 0
                ? round(($runsCompleted / $completionBase) * 100, 1)
                : 0.0;

            $result[$entityId] = [
                'process_total' => $total,
                'process_active' => $active,
                'process_runs_active' => $runsActive,
                'process_runs_completed' => $runsCompleted,
                'process_avg_cycle_minutes' => ! empty($cycleSums)
                    ? round(array_sum($cycleSums) / count($cycleSums), 1)
                    : 0,
                'process_focus_count' => $focus,
                'process_runs_started_7d' => $runsStarted7d,
                'process_runs_stalled' => $runsStalled,
                'process_runs_cancelled_30d' => $runsCancelled30d,
                'process_completion_rate_30d' => $completionRate,
            ];
        }

        return $result;
    }

    public function activityChildren(string $morphAlias, array $linkableIds): array
    {
        return [];
    }

    public function metricDefinitions(): array
    {
        return [
            // Bestehende Basis-KPIs — basis explizit gesetzt (fehlte vorher).
            'process_total' => [
                'label' => 'Prozesse (gesamt)', 'group' => 'process', 'direction' => 'neutral',
                'unit' => 'count', 'dimension' => 'complexity', 'type' => 'stock',
                'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag',
            ],
            'process_active' => [
                'label' => 'Prozesse (aktiv)', 'group' => 'process', 'direction' => 'up',
                'unit' => 'count', 'pair' => 'process_total', 'dimension' => 'complexity',
                'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag',
            ],
            'process_runs_active' => [
                'label' => 'Laufende Durchläufe', 'group' => 'process', 'direction' => 'neutral',
                'unit' => 'count', 'dimension' => 'energy', 'type' => 'stock',
                'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag',
            ],
            'process_runs_completed' => [
                'label' => 'Abgeschlossene Durchläufe (30 Tage)', 'group' => 'process',
                'direction' => 'up', 'unit' => 'count', 'dimension' => 'throughput',
                'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'window_30d',
            ],
            'process_avg_cycle_minutes' => [
                'label' => 'Ø Durchlaufzeit', 'group' => 'process', 'direction' => 'down',
                'unit' => 'minutes', 'dimension' => 'energy', 'type' => 'modulator',
                'aggregation_mode' => 'rolled_up', 'roll_up_function' => 'avg',
                'basis' => 'modulator_factor',
            ],

            // Neu: operationeller Puls.
            'process_focus_count' => [
                'label' => 'Fokus-Prozesse', 'group' => 'process', 'direction' => 'neutral',
                'unit' => 'count', 'dimension' => 'potential', 'type' => 'stock',
                'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag',
            ],
            'process_runs_started_7d' => [
                'label' => 'Gestartete Durchläufe (7 Tage)', 'group' => 'process',
                'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'energy',
                'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'window_7d',
            ],
            'process_runs_stalled' => [
                'label' => 'Ins Stocken geratene Durchläufe', 'group' => 'process',
                'direction' => 'down', 'unit' => 'count', 'dimension' => 'quality',
                'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag',
            ],
            'process_runs_cancelled_30d' => [
                'label' => 'Abgebrochene Durchläufe (30 Tage)', 'group' => 'process',
                'direction' => 'down', 'unit' => 'count', 'dimension' => 'quality',
                'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'window_30d',
            ],
            'process_completion_rate_30d' => [
                'label' => 'Erfolgsquote Durchläufe (30 Tage)', 'group' => 'process',
                'direction' => 'up', 'unit' => 'percentage', 'dimension' => 'quality',
                'type' => 'modulator', 'aggregation_mode' => 'rolled_up',
                'roll_up_function' => 'avg', 'basis' => 'modulator_factor',
            ],
        ];
    }
}
