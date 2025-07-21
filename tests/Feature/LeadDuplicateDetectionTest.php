<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;

beforeEach(function () {
    // Create a fresh in-memory SQLite database for each test
    config(['database.connections.sqlite.database' => ':memory:']);
    config(['database.default' => 'sqlite']);

    // Create only the essential tables we need for duplicate detection
    Schema::dropIfExists('leads');
    Schema::dropIfExists('lead_types');
    Schema::dropIfExists('lead_sources');
    Schema::dropIfExists('users');

    Schema::create('users', function ($table) {
        $table->increments('id');
        $table->string('name');
        $table->string('email');
        $table->string('password');
        $table->boolean('status')->default(true);
        $table->integer('role_id')->unsigned()->nullable();
        $table->timestamps();
    });

    // Create reference tables that the factory needs
    Schema::create('lead_sources', function ($table) {
        $table->increments('id');
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('lead_types', function ($table) {
        $table->increments('id');
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('leads', function ($table) {
        $table->increments('id');
        $table->string('title');
        $table->text('description')->nullable();
        $table->decimal('lead_value', 10, 2)->nullable();
        $table->boolean('status')->default(true);
        $table->text('lost_reason')->nullable();
        $table->timestamp('expected_close_date')->nullable();
        $table->timestamp('closed_at')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->json('emails')->nullable();
        $table->json('phones')->nullable();
        $table->integer('user_id')->unsigned()->nullable();
        $table->integer('person_id')->unsigned()->nullable();
        $table->integer('lead_source_id')->unsigned()->nullable();
        $table->integer('lead_type_id')->unsigned()->nullable();
        $table->integer('lead_pipeline_id')->unsigned()->nullable();
        $table->integer('lead_pipeline_stage_id')->unsigned()->nullable();
        $table->timestamps();
    });

    // Disable observers during testing to avoid database dependency issues
    \Webkul\Lead\Models\Lead::unsetEventDispatcher();

    $this->leadRepository = app(LeadRepository::class);
});

test('it detects duplicate leads by email', function () {
    // Create the first lead
    $lead1 = Lead::factory()->create([
        'title'      => 'Email Test Lead 1',
        'first_name' => 'Marcus',
        'last_name'  => 'Emailtest',
        'emails'     => [
            ['value' => 'shared.email@example.com', 'label' => 'work'],
        ],
    ]);

    // Create a second lead with the same email but different name
    $lead2 = Lead::factory()->create([
        'title'      => 'Email Test Lead 2',
        'first_name' => 'Natasha',
        'last_name'  => 'Differentname',
        'emails'     => [
            ['value' => 'shared.email@example.com', 'label' => 'home'],
        ],
    ]);

    // Test duplicate detection
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead1);

    $this->assertCount(1, $duplicates);
    $this->assertEquals($lead2->id, $duplicates->first()->id);
    $this->assertTrue($lead1->hasPotentialDuplicates());
    $this->assertEquals(1, $lead1->getPotentialDuplicatesCount());
});

test('it detects duplicate leads by phone', function () {
    // Create the first lead
    $lead1 = Lead::factory()->create([
        'title'      => 'Phone Test Lead 1',
        'first_name' => 'Alexander',
        'last_name'  => 'Phonetest',
        'phones'     => [
            ['value' => '+1234567890', 'label' => 'mobile'],
        ],
    ]);

    // Create a second lead with the same phone but different name
    $lead2 = Lead::factory()->create([
        'title'      => 'Phone Test Lead 2',
        'first_name' => 'Bethany',
        'last_name'  => 'Differentname',
        'phones'     => [
            ['value' => '+1234567890', 'label' => 'work'],
        ],
    ]);

    // Test duplicate detection
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead1);

    $this->assertCount(1, $duplicates);
    $this->assertEquals($lead2->id, $duplicates->first()->id);
});

test('it detects duplicate leads by name similarity', function () {
    // Create the first lead
    $lead1 = Lead::factory()->create([
        'title'      => 'Name Similarity Test Lead 1',
        'first_name' => 'John',
        'last_name'  => 'Similaritytest',
    ]);

    // Create a second lead with similar name (nickname variation)
    $lead2 = Lead::factory()->create([
        'title'      => 'Name Similarity Test Lead 2',
        'first_name' => 'Johnny',
        'last_name'  => 'Similaritytest',
    ]);

    // Test duplicate detection
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead1);

    $this->assertCount(1, $duplicates);
    $this->assertEquals($lead2->id, $duplicates->first()->id);
});

test('it detects duplicate leads by full name', function () {
    // Create the first lead
    $lead1 = Lead::factory()->create([
        'title'      => 'Full Name Test Lead 1',
        'first_name' => 'Gabriel',
        'last_name'  => 'Fullnametest',
    ]);

    // Create a second lead with the exact same full name
    $lead2 = Lead::factory()->create([
        'title'      => 'Full Name Test Lead 2',
        'first_name' => 'Gabriel',
        'last_name'  => 'Fullnametest',
    ]);

    // Test duplicate detection
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead1);

    $this->assertCount(1, $duplicates);
    $this->assertEquals($lead2->id, $duplicates->first()->id);
});

/** @test */
test('it returns empty collection when no duplicates exist', function () {
    // Create a lead with very unique data that shouldn't match anything
    $lead = Lead::factory()->create([
        'title'      => 'Absolutely Unique Lead XYZ123',
        'first_name' => 'Zephyr',
        'last_name'  => 'Quintessential',
        'emails'     => [
            ['value' => 'zephyr.quintessential.unique.test@example.com', 'label' => 'work'],
        ],
        'phones'     => [
            ['value' => '+9999999999', 'label' => 'mobile'],
        ],
    ]);

    // Test no duplicates
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead);

    $this->assertCount(0, $duplicates);
    $this->assertFalse($lead->hasPotentialDuplicates());
    $this->assertEquals(0, $lead->getPotentialDuplicatesCount());
});

test('it excludes self from duplicate detection', function () {
    // Create a lead
    $lead = Lead::factory()->create([
        'title'      => 'Self Exclusion Test Lead',
        'first_name' => 'Selftest',
        'last_name'  => 'Exclusion',
        'emails'     => [
            ['value' => 'selftest.exclusion@example.com', 'label' => 'work'],
        ],
    ]);

    // Test that the lead doesn't find itself as a duplicate
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead);

    $this->assertCount(0, $duplicates);
    $this->assertFalse($duplicates->contains('id', $lead->id));
});
