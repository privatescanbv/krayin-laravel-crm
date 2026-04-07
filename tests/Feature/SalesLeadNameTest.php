<?php

use App\Models\SalesLead;

test('sales lead name is stored and retrieved correctly', function () {
    $salesLead = SalesLead::factory()->create([
        'name' => 'Orthopedie behandeling',
    ]);

    expect($salesLead->fresh()->name)->toBe('Orthopedie behandeling');
});
