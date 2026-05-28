<?php

namespace Platform\Process;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Process\Console\Commands\DetectProcessChainsCommand;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ProcessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DetectProcessChainsCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        // Morph-Map: alte Aliases -> neue Klassen (preserviert polymorphe Referenzen)
        Relation::morphMap([
            'organization_process'               => \Platform\Process\Models\Process::class,
            'organization_process_step'          => \Platform\Process\Models\ProcessStep::class,
            'organization_process_chain'         => \Platform\Process\Models\ProcessChain::class,
            'organization_process_chain_member'  => \Platform\Process\Models\ProcessChainMember::class,
            'organization_process_run'           => \Platform\Process\Models\ProcessRun::class,
            'organization_process_run_step'      => \Platform\Process\Models\ProcessRunStep::class,
        ]);

        // Config laden
        $this->mergeConfigFrom(__DIR__.'/../config/process.php', 'process');

        // Modul registrieren
        if (
            config()->has('process.routing') &&
            config()->has('process.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'process',
                'title'      => 'Prozesse',
                'group'      => 'admin',
                'routing'    => config('process.routing'),
                'guard'      => config('process.guard'),
                'navigation' => config('process.navigation'),
                'sidebar'    => config('process.sidebar'),
            ]);
        }

        // Routes laden
        if (PlatformCore::getModule('process')) {
            ModuleRouter::group('process', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/guest.php');
            }, requireAuth: false);

            ModuleRouter::group('process', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }

        // Migrationen laden
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Config veröffentlichen
        $this->publishes([
            __DIR__.'/../config/process.php' => config_path('process.php'),
        ], 'config');

        // Views & Livewire
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'process');
        $this->registerLivewireComponents();

        // Tools registrieren
        $this->registerTools();

        // Error Reporter
        try {
            resolve(\Platform\Core\Services\ErrorReporterRegistry::class)
                ->register('process', 'Platform\\Process');
        } catch (\Throwable $e) {}

        // Scheduler
        $this->registerSchedule();
    }

    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\Process\\Livewire';
        $prefix = 'process';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            if (!class_exists($class)) {
                continue;
            }

            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }

    protected function registerSchedule(): void
    {
        Schedule::command('process:detect-process-chains')
            ->dailyAt('02:00')
            ->onOneServer()
            ->withoutOverlapping();
    }

    protected function registerTools(): void
    {
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);

            // Process Tools (Prozess-Definition)
            $registry->register(new \Platform\Process\Tools\ListProcessesTool());
            $registry->register(new \Platform\Process\Tools\CreateProcessTool());
            $registry->register(new \Platform\Process\Tools\UpdateProcessTool());
            $registry->register(new \Platform\Process\Tools\DeleteProcessTool());

            // Process Step Tools
            $registry->register(new \Platform\Process\Tools\ListProcessStepsTool());
            $registry->register(new \Platform\Process\Tools\CreateProcessStepTool());
            $registry->register(new \Platform\Process\Tools\BulkCreateProcessStepsTool());
            $registry->register(new \Platform\Process\Tools\UpdateProcessStepTool());
            $registry->register(new \Platform\Process\Tools\DeleteProcessStepTool());

            // Process Flow Tools
            $registry->register(new \Platform\Process\Tools\ListProcessFlowsTool());
            $registry->register(new \Platform\Process\Tools\CreateProcessFlowTool());
            $registry->register(new \Platform\Process\Tools\BulkCreateProcessFlowsTool());
            $registry->register(new \Platform\Process\Tools\UpdateProcessFlowTool());
            $registry->register(new \Platform\Process\Tools\DeleteProcessFlowTool());
            $registry->register(new \Platform\Process\Tools\BulkDeleteProcessFlowsTool());

            // Process Trigger Tools
            $registry->register(new \Platform\Process\Tools\ListProcessTriggersTool());
            $registry->register(new \Platform\Process\Tools\CreateProcessTriggerTool());
            $registry->register(new \Platform\Process\Tools\UpdateProcessTriggerTool());
            $registry->register(new \Platform\Process\Tools\DeleteProcessTriggerTool());

            // Process Output Tools
            $registry->register(new \Platform\Process\Tools\ListProcessOutputsTool());
            $registry->register(new \Platform\Process\Tools\CreateProcessOutputTool());
            $registry->register(new \Platform\Process\Tools\UpdateProcessOutputTool());
            $registry->register(new \Platform\Process\Tools\DeleteProcessOutputTool());

            // Process Step Entity Tools
            $registry->register(new \Platform\Process\Tools\ListProcessStepEntitiesTool());
            $registry->register(new \Platform\Process\Tools\CreateProcessStepEntityTool());
            $registry->register(new \Platform\Process\Tools\UpdateProcessStepEntityTool());
            $registry->register(new \Platform\Process\Tools\DeleteProcessStepEntityTool());

            // Process Step Interlink Tools
            $registry->register(new \Platform\Process\Tools\ListProcessStepInterlinksTool());
            $registry->register(new \Platform\Process\Tools\CreateProcessStepInterlinkTool());
            $registry->register(new \Platform\Process\Tools\UpdateProcessStepInterlinkTool());
            $registry->register(new \Platform\Process\Tools\DeleteProcessStepInterlinkTool());

            // Process Snapshot Tools
            $registry->register(new \Platform\Process\Tools\CreateProcessSnapshotTool());
            $registry->register(new \Platform\Process\Tools\ListProcessSnapshotsTool());
            $registry->register(new \Platform\Process\Tools\GetProcessSnapshotTool());
            $registry->register(new \Platform\Process\Tools\CompareProcessSnapshotsTool());

            // Process Improvement Tools
            $registry->register(new \Platform\Process\Tools\CreateProcessImprovementTool());
            $registry->register(new \Platform\Process\Tools\ListProcessImprovementsTool());
            $registry->register(new \Platform\Process\Tools\UpdateProcessImprovementTool());
            $registry->register(new \Platform\Process\Tools\DeleteProcessImprovementTool());

            // Process Chain Tools
            $registry->register(new \Platform\Process\Tools\ListProcessChainsTool());
            $registry->register(new \Platform\Process\Tools\CreateProcessChainTool());
            $registry->register(new \Platform\Process\Tools\UpdateProcessChainTool());
            $registry->register(new \Platform\Process\Tools\DeleteProcessChainTool());
            $registry->register(new \Platform\Process\Tools\AddProcessToChainTool());
            $registry->register(new \Platform\Process\Tools\RemoveProcessFromChainTool());
            $registry->register(new \Platform\Process\Tools\DetectProcessChainsTool());

            // Process Run Tools
            $registry->register(new \Platform\Process\Tools\ListProcessRunsTool());
            $registry->register(new \Platform\Process\Tools\CreateProcessRunTool());
            $registry->register(new \Platform\Process\Tools\UpdateProcessRunTool());
            $registry->register(new \Platform\Process\Tools\CompleteProcessRunStepTool());

        } catch (\Throwable $e) {
            \Log::warning('Process: Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }
    }
}
