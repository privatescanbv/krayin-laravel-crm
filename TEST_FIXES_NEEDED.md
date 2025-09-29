# Test Fixes Needed (Separate from Permission Fix)

Deze test failures zijn **niet gerelateerd** aan de log permission fix en moeten apart worden aangepakt.

## 1. BuildMergedWeeklySummaryTest - BindingResolutionException

**Issue**: `Target class [config] does not exist.`

**Locatie**: `tests/Unit/BuildMergedWeeklySummaryTest.php:12`

**Oorzaak**: De test probeert een dependency te resolven die niet correct is geregistreerd.

**Fix Suggestie**:
```php
// In test file, zorg dat dependencies correct gemocked worden
protected function setUp(): void
{
    parent::setUp();
    // Mock de config dependency
}
```

## 2. ClinicCrudTest::can delete clinic - 302 Redirect

**Issue**: Verwacht 200 status, krijgt 302 (redirect)

**Locatie**: `tests/Feature/Settings/ClinicCrudTest.php:65`

**Oorzaak**: Waarschijnlijk ontbrekende authenticatie in test.

**Fix Suggestie**:
```php
test('can delete clinic', function () {
    // Voeg authenticatie toe
    $user = User::factory()->create();
    $this->actingAs($user);
    
    $clinic = Clinic::factory()->create();
    $response = $this->deleteJson(route('admin.settings.clinics.delete', ['id' => $clinic->id]));
    $response->assertOk();
    
    $this->assertDatabaseMissing('clinics', [
        'id' => $clinic->id,
    ]);
});
```

## 3. ResourceCrudTest - Missing clinic_id

**Issue**: `The clinic id field is required.` (422 status)

**Locatie**: 
- `tests/Feature/Settings/ResourceCrudTest.php:37` (create)
- `tests/Feature/Settings/ResourceCrudTest.php:54` (update)

**Oorzaak**: Test payload mist verplichte `clinic_id` veld.

**Fix Suggestie**:
```php
test('can create resource', function () {
    $clinic = Clinic::factory()->create();
    $resourceType = ResourceType::factory()->create();

    $payload = [
        'name'             => 'Test Resource',
        'resource_type_id' => $resourceType->id,
        'clinic_id'        => $clinic->id, // ← Voeg deze toe
    ];

    $response = $this->postJson(route('admin.settings.resources.store'), $payload);
    $response->assertOk()->assertJsonPath('data.name', 'Test Resource');
    
    $this->assertDatabaseHas('resources', [
        'name' => 'Test Resource',
    ]);
});

test('can update resource', function () {
    $clinic = Clinic::factory()->create();
    $resource = Resource::factory()->create(['clinic_id' => $clinic->id]);

    $payload = [
        'name'             => 'Updated Resource',
        'resource_type_id' => $resource->resource_type_id,
        'clinic_id'        => $clinic->id, // ← Voeg deze toe
        '_method'          => 'put',
    ];

    $response = $this->postJson(route('admin.settings.resources.update', ['id' => $resource->id]), $payload);
    $response->assertOk()->assertJsonPath('data.name', 'Updated Resource');
    
    $this->assertDatabaseHas('resources', [
        'id'   => $resource->id,
        'name' => 'Updated Resource',
    ]);
});
```

## Priority

Deze test fixes kunnen in een **aparte PR** worden aangepakt. Ze blokkeren niet de deployment van de log permission fix.

## Test Results Summary
- ✅ 212 tests passed
- ❌ 4 tests failed (niet gerelateerd aan permission fix)
- Total: 216 tests, 1008 assertions