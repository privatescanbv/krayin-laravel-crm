<?php

namespace App\Console\Commands;

use App\Enums\Departments;
use App\Enums\PipelineDefaultKeys;
use App\Models\Department;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

/**
 * Commands to create test data
 */
class SeedPrivatescanStageLeads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:seed-privatescan-stages
                            {--per-stage=15 : Number of leads per stage}
                            {--pipeline-id= : Lead pipeline ID (defaults to Privatescan lead pipeline)}
                            {--user-id= : Assign leads to this user}
                            {--with-events : Dispatch model events (observers, webhooks, etc.)}
                            {--dry-run : Show what would be created without writing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed performance leads for all Privatescan lead pipeline stages';

    public function handle(): int
    {
        $perStage = (int) $this->option('per-stage');
        $pipelineId = (int) ($this->option('pipeline-id') ?: PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value);
        $dryRun = (bool) $this->option('dry-run');
        $withEvents = (bool) $this->option('with-events');

        if ($perStage < 1) {
            $this->error('--per-stage must be >= 1');

            return 1;
        }

        /** @var Pipeline|null $pipeline */
        $pipeline = Pipeline::query()->find($pipelineId);
        if (! $pipeline) {
            $this->error("Pipeline {$pipelineId} not found. Did you run the pipeline seeder?");

            return 1;
        }

        $stages = Stage::query()
            ->where('lead_pipeline_id', $pipelineId)
            ->orderBy('sort_order')
            ->get();

        if ($stages->isEmpty()) {
            $this->error("No stages found for pipeline {$pipelineId}.");

            return 1;
        }

        $department = Department::query()->firstOrCreate(['name' => Departments::PRIVATESCAN->value]);

        $userIdOption = $this->option('user-id');
        /** @var User $user */
        $user = $userIdOption
            ? (User::query()->findOrFail((int) $userIdOption))
            : (User::query()->first() ?? User::factory()->create());

        $total = $stages->count() * $perStage;
        $this->info("Seeding {$total} leads ({$perStage} per stage) for pipeline '{$pipeline->name}' (#{$pipelineId}).");
        $this->info("Department: {$department->name} (id: {$department->id}) | User: {$user->id}");
        $this->info('Events: '.($withEvents ? 'enabled' : 'disabled'));

        if ($dryRun) {
            $this->warn('DRY RUN MODE - no leads will be created');
        }

        $progressBar = $this->output->createProgressBar($stages->count());
        $progressBar->start();

        $now = Carbon::now();

        $createForStage = function (Stage $stage) use ($perStage, $pipelineId, $department, $user, $now, $dryRun): int {
            $isClosedStage = (bool) $stage->is_won || (bool) $stage->is_lost;

            $overrides = [
                'department_id'          => $department->id,
                'user_id'                => $user->id,
                'lead_pipeline_id'       => $pipelineId,
                'lead_pipeline_stage_id' => $stage->id,
                'status'                 => $isClosedStage ? false : true,
                'closed_at'              => $isClosedStage ? $now : null,
            ];

            if ($dryRun) {
                $this->line("Would create {$perStage} leads for stage '{$stage->name}' ({$stage->code}, id: {$stage->id}).");

                return 0;
            }

            Lead::factory()
                ->count($perStage)
                ->create($overrides);

            return $perStage;
        };

        $created = 0;

        if (! $dryRun) {
            $run = function () use ($stages, $createForStage, &$created, $progressBar) {
                DB::transaction(function () use ($stages, $createForStage, &$created, $progressBar) {
                    foreach ($stages as $stage) {
                        $created += $createForStage($stage);
                        $progressBar->advance();
                    }
                });
            };

            if ($withEvents) {
                $run();
            } else {
                Lead::withoutEvents(fn () => $run());
            }
        } else {
            foreach ($stages as $stage) {
                $createForStage($stage);
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        if (! $dryRun) {
            $this->info("Done. Created {$created} leads.");
        }

        return 0;
    }
}
