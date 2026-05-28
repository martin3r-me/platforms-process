<?php

namespace Platform\Process\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Process\Models\ProcessFlow;
use Platform\Process\Tools\Concerns\ResolvesProcessTeam;

class DeleteProcessFlowTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesProcessTeam;

    public function getName(): string
    {
        return 'process.process_flows.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /process/process-flows/{id} - Löscht einen Prozess-Flow (soft delete).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'         => ['type' => 'integer'],
                'process_flow_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
            ],
            'required' => ['process_flow_id'],
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
                'process_flow_id',
                ProcessFlow::class,
                'NOT_FOUND',
                'Prozess-Flow nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var ProcessFlow $flow */
            $flow = $found['model'];
            if ((int) $flow->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Prozess-Flow gehört nicht zum Root/Elterteam.');
            }

            $flow->delete();

            return ToolResult::success([
                'id'      => $flow->id,
                'message' => 'Prozess-Flow gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Prozess-Flows: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['process', 'process_flows', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
