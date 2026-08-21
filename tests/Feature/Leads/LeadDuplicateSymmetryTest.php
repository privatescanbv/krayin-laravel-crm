<?php

namespace Tests\Feature;

use App\Enums\ContactLabel;
use Database\Seeders\TestSeeder;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Repositories\LeadRepository;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->leadRepository = app(LeadRepository::class);
});

/**
 * Duplicate detection must report a pair from both sides: if A shows up on B, B shows up on A.
 */
function duplicateIdsFor(Lead $lead): array
{
    return app(LeadRepository::class)->findPotentialDuplicates($lead)->pluck('id')->sort()->values()->toArray();
}

test('an old and a new lead see each other', function () {
    $email = fn (string $label) => [['value' => 'symmetry@example.com', 'label' => $label]];

    $old = Lead::factory()->create([
        'first_name' => 'Old',
        'last_name'  => 'Sideofpair',
        'emails'     => $email(ContactLabel::Eigen->value),
        'created_at' => now()->subWeeks(6),
    ]);

    $new = Lead::factory()->create([
        'first_name' => 'New',
        'last_name'  => 'Sideofpair',
        'emails'     => $email(ContactLabel::Relatie->value),
        'created_at' => now(),
    ]);

    expect(duplicateIdsFor($old))->toBe([$new->id])
        ->and(duplicateIdsFor($new))->toBe([$old->id]);
});

test('a lead created later invalidates the cached result of its counterpart', function () {
    $first = Lead::factory()->create([
        'first_name' => 'Cached',
        'last_name'  => 'Counterpart',
        'emails'     => [['value' => 'cache.symmetry@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);

    // Warm the cache while the lead has no duplicates yet - this is what used to stick for an hour.
    expect(duplicateIdsFor($first))->toBe([]);

    $second = Lead::factory()->create([
        'first_name' => 'Cached',
        'last_name'  => 'Counterpart',
        'emails'     => [['value' => 'cache.symmetry@example.com', 'label' => ContactLabel::Relatie->value]],
    ]);

    expect(duplicateIdsFor($first))->toBe([$second->id])
        ->and(duplicateIdsFor($second))->toBe([$first->id]);
});

test('a closed lead reports no duplicates itself either', function (string $stageCode) {
    $stage = Stage::where('code', $stageCode)->where('lead_pipeline_id', 1)->firstOrFail();

    $closed = Lead::factory()->create([
        'first_name'             => 'Closed',
        'last_name'              => 'Stagetest',
        'emails'                 => [['value' => 'closed.stage@example.com', 'label' => ContactLabel::Eigen->value]],
        'lead_pipeline_stage_id' => $stage->id,
    ]);

    $open = Lead::factory()->create([
        'first_name' => 'Open',
        'last_name'  => 'Stagetest',
        'emails'     => [['value' => 'closed.stage@example.com', 'label' => ContactLabel::Relatie->value]],
    ]);

    expect(duplicateIdsFor($closed))->toBe([])
        ->and(duplicateIdsFor($open))->toBe([]);
})->with(['won', 'lost']);

test('a married name matches from both sides', function () {
    $birthName = Lead::factory()->create([
        'first_name' => 'Marie',
        'last_name'  => 'Vries',
    ]);

    $marriedName = Lead::factory()->create([
        'first_name'   => 'Marie',
        'last_name'    => 'Jansen',
        'married_name' => 'Vries',
    ]);

    expect(duplicateIdsFor($birthName))->toBe([$marriedName->id])
        ->and(duplicateIdsFor($marriedName))->toBe([$birthName->id]);
});
