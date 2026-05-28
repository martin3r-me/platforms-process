<?php

namespace Platform\Process\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Process\Models\ProcessStep;
use Platform\Process\Tools\Concerns\ResolvesProcessTeam;

class DeleteProcessStepTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesProcessTeam;

    public function getName(): string
    {
        return 'process.process_steps.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /process/process-steps/{id} - Löscht einen Prozess-Schritt (soft delete). Flows, StepEntities und StepInterlinks werden kaskadiert gelöscht.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'         => ['type' => 'integer'],
                'process_step_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
            ],
            'required' => ['process_step_id'],
        ]);
    }

    protected function getAccessAction(): string
    {
        return 'delete';
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'process_step_id',
                ProcessStep::class,
                'NOT_FOUND',
                'Prozess-Schritt nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var ProcessStep $step */
            $step = $found['model'];
            if ((int) $step->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Prozess-Schritt gehört nicht zum Root/Elterteam.');
            }

            $step->delete();

            return ToolResult::success([
                'id'      => $step->id,
                'message' => 'Prozess-Schritt gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Prozess-Schritts: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['process', 'process_steps', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
