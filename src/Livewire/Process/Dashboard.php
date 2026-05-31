<?php

namespace Platform\Process\Livewire\Process;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Process\Enums\AutomationLevel;
use Platform\Process\Enums\ProcessCategory;
use Platform\Process\Enums\ProcessStatus;
use Platform\Process\Models\Process;
use Platform\Process\Models\ProcessRun;
use Platform\Process\Models\ProcessStep;

class Dashboard extends Component
{
    protected function teamId(): ?int
    {
        return Auth::user()?->currentTeam?->id;
    }

    #[Computed]
    public function totalProcesses(): int
    {
        return Process::where('team_id', $this->teamId())
            ->where('is_active', true)
            ->count();
    }

    #[Computed]
    public function statusCounts(): array
    {
        $counts = Process::where('team_id', $this->teamId())
            ->where('is_active', true)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $result = [];
        foreach (ProcessStatus::cases() as $status) {
            $result[] = [
                'status' => $status,
                'label' => $status->label(),
                'color' => $status->color(),
                'count' => $counts[$status->value] ?? 0,
            ];
        }

        return $result;
    }

    #[Computed]
    public function categoryCounts(): array
    {
        $counts = Process::where('team_id', $this->teamId())
            ->where('is_active', true)
            ->whereNotNull('process_category')
            ->selectRaw('process_category, COUNT(*) as count')
            ->groupBy('process_category')
            ->pluck('count', 'process_category')
            ->toArray();

        $uncategorized = Process::where('team_id', $this->teamId())
            ->where('is_active', true)
            ->whereNull('process_category')
            ->count();

        $result = [];
        foreach (ProcessCategory::cases() as $cat) {
            $result[] = [
                'label' => $cat->label(),
                'color' => $cat->color(),
                'count' => $counts[$cat->value] ?? 0,
            ];
        }

        if ($uncategorized > 0) {
            $result[] = [
                'label' => 'Ohne Kategorie',
                'color' => 'muted',
                'count' => $uncategorized,
            ];
        }

        return $result;
    }

    #[Computed]
    public function automationScore(): float
    {
        $teamId = $this->teamId();

        $steps = ProcessStep::whereHas('process', function ($q) use ($teamId) {
            $q->where('team_id', $teamId)->where('is_active', true);
        })->whereNotNull('automation_level')->get();

        if ($steps->isEmpty()) {
            return 0;
        }

        $totalScore = $steps->sum(fn ($s) => $s->automation_level->scoreWeight());

        return round($totalScore / $steps->count(), 1);
    }

    #[Computed]
    public function focusProcesses()
    {
        return Process::where('team_id', $this->teamId())
            ->where('is_active', true)
            ->where('is_focus', true)
            ->orderBy('focus_until')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function activeRuns()
    {
        return ProcessRun::where('team_id', $this->teamId())
            ->where('status', 'active')
            ->with('process:id,name,status')
            ->orderByDesc('started_at')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function recentProcesses()
    {
        return Process::where('team_id', $this->teamId())
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();
    }

    public function render()
    {
        return view('process::livewire.process.dashboard')
            ->layout('platform::layouts.app');
    }
}
