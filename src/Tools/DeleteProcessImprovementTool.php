<?php

namespace Platform\Process\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Process\Models\ProcessImprovement;
use Platform\Process\Tools\Concerns\ResolvesProcessTeam;

class DeleteProcessImprovementTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesProcessTeam;

    public function getName(): string
    {
        return 'process.process_improvements.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /process/process_improvements/{id} - Löscht eine Verbesserung (soft delete).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'        => ['type' => 'integer'],
                'improvement_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
            ],
            'required' => ['improvement_id'],
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
                'improvement_id',
                ProcessImprovement::class,
                'NOT_FOUND',
                'Verbesserung nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationProcessImprovement $improvement */
            $improvement = $found['model'];
            if ((int) $improvement->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Verbesserung gehört nicht zum Team.');
            }

            $improvement->delete();

            return ToolResult::success([
                'id'      => $improvement->id,
                'message' => 'Verbesserung gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Verbesserung: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['process', 'processes', 'improvements', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
