<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename tables order: leaf tables first (no dependents), then parent tables.
     */
    private const RENAME_MAP = [
        // Leaf tables first
        'organization_process_run_steps'       => 'process_run_steps',
        'organization_process_runs'            => 'process_runs',
        'organization_process_chain_members'   => 'process_chain_members',
        'organization_process_chains'          => 'process_chains',
        'organization_process_improvements'    => 'process_improvements',
        'organization_process_snapshots'       => 'process_snapshots',
        'organization_process_step_interlinks' => 'process_step_interlinks',
        'organization_process_step_entities'   => 'process_step_entities',
        'organization_process_outputs'         => 'process_outputs',
        'organization_process_triggers'        => 'process_triggers',
        'organization_process_flows'           => 'process_flows',
        'organization_process_steps'           => 'process_steps',
        // Parent table last
        'organization_processes'               => 'processes',
    ];

    public function up(): void
    {
        if (Schema::hasTable('organization_processes')) {
            $this->renameExistingTables();
        } else {
            $this->createFreshTables();
        }
    }

    public function down(): void
    {
        // Reverse rename: new -> old
        foreach (array_reverse(self::RENAME_MAP) as $old => $new) {
            if (Schema::hasTable($new) && ! Schema::hasTable($old)) {
                Schema::rename($new, $old);
            }
        }
    }

    private function renameExistingTables(): void
    {
        foreach (self::RENAME_MAP as $old => $new) {
            if (Schema::hasTable($old) && ! Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }
    }

    private function createFreshTables(): void
    {
        // 1. processes
        Schema::create('processes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('owner_entity_id')->nullable();
            $table->foreign('owner_entity_id')->references('id')->on('organization_entities')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->string('process_category')->nullable();
            $table->boolean('is_focus')->default(false);
            $table->text('focus_reason')->nullable();
            $table->date('focus_until')->nullable();
            $table->json('metadata')->nullable();
            $table->text('target_description')->nullable();
            $table->text('value_proposition')->nullable();
            $table->text('cost_analysis')->nullable();
            $table->text('risk_assessment')->nullable();
            $table->text('improvement_levers')->nullable();
            $table->text('action_plan')->nullable();
            $table->text('standardization_notes')->nullable();
            $table->decimal('hourly_rate', 12, 2)->nullable();
            $table->string('public_token', 64)->nullable()->unique();
            $table->timestamp('public_token_expires_at')->nullable();
            $table->string('frequency')->nullable();
            $table->text('process_landscape')->nullable();
            $table->text('corefit_classification_notes')->nullable();
            $table->json('workshop_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'is_active', 'deleted_at']);
            $table->index(['status', 'team_id']);
            $table->index('owner_entity_id');
        });

        // 2. process_steps
        Schema::create('process_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('position');
            $table->string('step_type')->default('action');
            $table->string('gateway_type')->nullable();
            $table->string('event_type')->nullable();
            $table->unsignedInteger('duration_target_minutes')->nullable();
            $table->unsignedInteger('wait_target_minutes')->nullable();
            $table->decimal('external_cost_per_run', 12, 2)->nullable();
            $table->string('corefit_classification')->nullable();
            $table->string('automation_level')->nullable();
            $table->string('complexity')->nullable();
            $table->json('llm_tools')->nullable();
            $table->unsignedBigInteger('sub_process_id')->nullable();
            $table->foreign('sub_process_id')->references('id')->on('processes')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['process_id', 'position']);
            $table->index(['process_id', 'is_active']);
            $table->index(['team_id', 'deleted_at']);
            $table->index(['process_id', 'step_type', 'gateway_type'], 'idx_steps_process_gateway');
            $table->index(['process_id', 'step_type', 'event_type'], 'idx_steps_process_event');
            $table->index(['process_id', 'complexity'], 'idx_steps_process_complexity');
        });

        // 3. process_flows
        Schema::create('process_flows', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();
            $table->foreignId('from_step_id')->constrained('process_steps')->cascadeOnDelete();
            $table->foreignId('to_step_id')->constrained('process_steps')->cascadeOnDelete();
            $table->string('condition_label')->nullable();
            $table->json('condition_expression')->nullable();
            $table->string('flow_kind')->default('sequence');
            $table->unsignedTinyInteger('priority')->default(100);
            $table->boolean('is_default')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['from_step_id', 'to_step_id']);
            $table->index('process_id');
            $table->index('to_step_id');
            $table->index(['from_step_id', 'priority'], 'idx_flows_from_priority');
            $table->index(['process_id', 'flow_kind'], 'idx_flows_process_kind');
        });

        // 4. process_triggers
        Schema::create('process_triggers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('trigger_type');
            $table->unsignedBigInteger('entity_type_id')->nullable();
            $table->foreign('entity_type_id')->references('id')->on('organization_entity_types')->nullOnDelete();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->foreign('entity_id')->references('id')->on('organization_entities')->nullOnDelete();
            $table->unsignedBigInteger('source_process_id')->nullable();
            $table->foreign('source_process_id')->references('id')->on('processes')->nullOnDelete();
            $table->unsignedBigInteger('interlink_id')->nullable();
            $table->foreign('interlink_id')->references('id')->on('organization_interlinks')->nullOnDelete();
            $table->string('schedule_expression')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('process_id');
            $table->index('entity_type_id');
            $table->index('source_process_id');
            $table->index('interlink_id');
        });

        // 5. process_outputs
        Schema::create('process_outputs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('output_type');
            $table->unsignedBigInteger('entity_type_id')->nullable();
            $table->foreign('entity_type_id')->references('id')->on('organization_entity_types')->nullOnDelete();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->foreign('entity_id')->references('id')->on('organization_entities')->nullOnDelete();
            $table->unsignedBigInteger('target_process_id')->nullable();
            $table->foreign('target_process_id')->references('id')->on('processes')->nullOnDelete();
            $table->unsignedBigInteger('interlink_id')->nullable();
            $table->foreign('interlink_id')->references('id')->on('organization_interlinks')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('process_id');
            $table->index('entity_type_id');
            $table->index('target_process_id');
            $table->index('interlink_id');
        });

        // 6. process_step_entities
        Schema::create('process_step_entities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('process_step_id')->constrained('process_steps')->cascadeOnDelete();
            $table->unsignedBigInteger('entity_type_id')->nullable();
            $table->foreign('entity_type_id')->references('id')->on('organization_entity_types')->nullOnDelete();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->foreign('entity_id')->references('id')->on('organization_entities')->nullOnDelete();
            $table->string('role');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['process_step_id', 'entity_type_id', 'entity_id', 'role'], 'proc_pse_unique');
            $table->index('entity_type_id');
            $table->index('entity_id');
        });

        // 7. process_step_interlinks
        Schema::create('process_step_interlinks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('process_step_id')->constrained('process_steps')->cascadeOnDelete();
            $table->unsignedBigInteger('interlink_id');
            $table->foreign('interlink_id')->references('id')->on('organization_interlinks')->cascadeOnDelete();
            $table->string('role');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['process_step_id', 'interlink_id', 'role'], 'proc_psi_unique');
            $table->index('interlink_id');
        });

        // 8. process_snapshots
        Schema::create('process_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('label')->nullable();
            $table->json('snapshot_data');
            $table->json('metrics')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['process_id', 'version']);
        });

        // 9. process_improvements
        Schema::create('process_improvements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category');
            $table->string('priority')->default('medium');
            $table->string('status')->default('identified');
            $table->text('expected_outcome')->nullable();
            $table->text('actual_outcome')->nullable();
            $table->unsignedBigInteger('before_snapshot_id')->nullable();
            $table->foreign('before_snapshot_id')->references('id')->on('process_snapshots')->nullOnDelete();
            $table->unsignedBigInteger('after_snapshot_id')->nullable();
            $table->foreign('after_snapshot_id')->references('id')->on('process_snapshots')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('target_step_id')->nullable();
            $table->foreign('target_step_id')->references('id')->on('process_steps')->nullOnDelete();
            $table->integer('projected_duration_target_minutes')->nullable();
            $table->string('projected_automation_level')->nullable();
            $table->string('projected_complexity')->nullable();
            $table->decimal('projected_hourly_rate', 10, 2)->nullable();
            $table->string('savings_type')->nullable();
            $table->decimal('projected_external_cost_per_run', 12, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['process_id', 'status']);
            $table->index(['team_id', 'category']);
            $table->index(['process_id', 'target_step_id'], 'proc_improv_process_target_step_idx');
        });

        // 10. process_runs
        Schema::create('process_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['process_id', 'status']);
            $table->index(['team_id', 'status']);
        });

        // 11. process_run_steps
        Schema::create('process_run_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('run_id')->constrained('process_runs')->cascadeOnDelete();
            $table->foreignId('process_step_id')->constrained('process_steps')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->integer('position');
            $table->integer('active_duration_minutes')->nullable();
            $table->integer('wait_duration_minutes')->nullable();
            $table->boolean('wait_override')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['run_id', 'position']);
            $table->unique(['run_id', 'process_step_id']);
        });

        // 12. process_chains
        Schema::create('process_chains', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->string('chain_type')->default('ad_hoc');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_auto_detected')->default(false);
            $table->unsignedBigInteger('entry_process_id')->nullable();
            $table->foreign('entry_process_id')->references('id')->on('processes')->nullOnDelete();
            $table->unsignedBigInteger('exit_process_id')->nullable();
            $table->foreign('exit_process_id')->references('id')->on('processes')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'code']);
            $table->index(['team_id', 'is_active', 'deleted_at']);
            $table->index(['team_id', 'chain_type']);
            $table->index(['team_id', 'is_auto_detected']);
        });

        // 13. process_chain_members
        Schema::create('process_chain_members', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chain_id')->constrained('process_chains')->cascadeOnDelete();
            $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('role')->default('middle');
            $table->boolean('is_required')->default(true);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['chain_id', 'process_id'], 'uq_proc_chain_process');
            $table->index(['chain_id', 'position']);
            $table->index(['chain_id', 'role']);
            $table->index(['team_id', 'deleted_at']);
        });
    }
};
