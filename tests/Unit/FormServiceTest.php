<?php

namespace Tests\Unit;

use App\Models\Anamnesis;
use App\Models\SalesLead;
use App\Services\FormService;
use App\Services\LeadAndSalesService;
use Mockery;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

test('findRelatedEntityByFormId returns lead only when no sales found', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $anamnesis = Anamnesis::factory()->create([
        'person_id'     => $person->id,
        'gvl_form_link' => 'https://forms.example.com/forms/1',
    ]);

    $mock = Mockery::mock(LeadAndSalesService::class);
    $mock->shouldReceive('findOpenByPerson')
        ->once()
        ->with($anamnesis->person_id)
        ->andReturn([
            'lead'  => $lead,
            'sales' => null,
        ]);

    app()->instance(LeadAndSalesService::class, $mock);

    $service = app(FormService::class);

    $result = $service->findRelatedEntityByFormId($anamnesis->gvl_form_link);

    expect($result['lead'])->not->toBeNull()
        ->and($result['lead']->id)->toBe($lead->id)
        ->and($result['sales'])->toBeNull()
        ->and($result['person_id'])->toBe($anamnesis->person_id);
});

test('findRelatedEntityByFormId returns sales only when no lead found', function () {
    $person = Person::factory()->create();
    $sales = SalesLead::factory()->create();
    $anamnesis = Anamnesis::factory()->create([
        'person_id'     => $person->id,
        'gvl_form_link' => 'https://forms.example.com/forms/2',
    ]);

    $mock = Mockery::mock(LeadAndSalesService::class);
    $mock->shouldReceive('findOpenByPerson')
        ->once()
        ->with($anamnesis->person_id)
        ->andReturn([
            'lead'  => null,
            'sales' => $sales,
        ]);

    app()->instance(LeadAndSalesService::class, $mock);

    $service = app(FormService::class);

    $result = $service->findRelatedEntityByFormId($anamnesis->gvl_form_link);

    expect($result['lead'])->toBeNull()
        ->and($result['sales'])->not->toBeNull()
        ->and($result['sales']->id)->toBe($sales->id)
        ->and($result['person_id'])->toBe($anamnesis->person_id);
});

test('findRelatedEntityByFormId returns nulls when neither lead nor sales found', function () {
    $person = Person::factory()->create();
    $anamnesis = Anamnesis::factory()->create([
        'person_id'     => $person->id,
        'gvl_form_link' => 'https://forms.example.com/forms/3',
    ]);

    $mock = Mockery::mock(LeadAndSalesService::class);
    $mock->shouldReceive('findOpenByPerson')
        ->once()
        ->with($anamnesis->person_id)
        ->andReturn([
            'lead'  => null,
            'sales' => null,
        ]);

    app()->instance(LeadAndSalesService::class, $mock);

    $service = app(FormService::class);

    $result = $service->findRelatedEntityByFormId($anamnesis->gvl_form_link);

    expect($result['lead'])->toBeNull()
        ->and($result['sales'])->toBeNull()
        ->and($result['person_id'])->toBe($anamnesis->person_id);
});
