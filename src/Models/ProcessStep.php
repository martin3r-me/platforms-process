<?php

namespace Platform\Process\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Process\Enums\AutomationLevel;
use Platform\Process\Enums\CorefitClassification;
use Platform\Process\Enums\ProcessEventType;
use Platform\Process\Enums\ProcessGatewayType;
use Platform\Process\Enums\StepComplexity;
use Symfony\Component\Uid\UuidV7;

class ProcessStep extends Model
{
    use SoftDeletes;

    protected $table = 'process_steps';

    protected $fillable = [
        'uuid', 'team_id', 'user_id', 'process_id', 'name', 'description',
        'position', 'step_type', 'gateway_type', 'event_type',
        'duration_target_minutes', 'wait_target_minutes', 'external_cost_per_run',
        'corefit_classification', 'automation_level', 'complexity', 'llm_tools',
        'sub_process_id', 'is_active', 'metadata',
    ];

    protected $casts = [
        'position' => 'integer',
        'duration_target_minutes' => 'integer',
        'wait_target_minutes' => 'integer',
        'external_cost_per_run' => 'decimal:2',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'llm_tools' => 'array',
        'corefit_classification' => CorefitClassification::class,
        'automation_level' => AutomationLevel::class,
        'gateway_type' => ProcessGatewayType::class,
        'event_type' => ProcessEventType::class,
        'complexity' => StepComplexity::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do { $uuid = UuidV7::generate(); } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
            if (! $model->user_id) { $model->user_id = Auth::id(); }
            if (! $model->team_id) { $model->team_id = Auth::user()?->currentTeamRelation?->id; }
        });
    }

    public function process(): BelongsTo { return $this->belongsTo(Process::class, 'process_id'); }
    public function subProcess(): BelongsTo { return $this->belongsTo(Process::class, 'sub_process_id'); }
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function outgoingFlows(): HasMany { return $this->hasMany(ProcessFlow::class, 'from_step_id'); }
    public function incomingFlows(): HasMany { return $this->hasMany(ProcessFlow::class, 'to_step_id'); }
    public function stepEntities(): HasMany { return $this->hasMany(ProcessStepEntity::class, 'process_step_id'); }
    public function stepInterlinks(): HasMany { return $this->hasMany(ProcessStepInterlink::class, 'process_step_id'); }
    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeForTeam($query, int $teamId) { return $query->where('team_id', $teamId); }
}
