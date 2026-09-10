<?php

use App\Models\Clinic;
use App\Models\Inkoop\InkoopInvoice;
use Webkul\User\Models\User;

test('inkoop invoice audit trail uses user guard', function () {
    $clinic = Clinic::factory()->create();
    $user1 = makeUser();
    $user2 = makeUser();

    $this->actingAs($user1, 'user');

    $invoice = InkoopInvoice::create([
        'clinic_id' => $clinic->id,
        'pdf_path'  => 'inkoop/test.pdf',
        'filename'  => 'test.pdf',
    ]);

    expect($invoice->created_by)->toBe($user1->id)
        ->and($invoice->updated_by)->toBe($user1->id)
        ->and($invoice->creator)->toBeInstanceOf(User::class)
        ->and($invoice->creator->id)->toBe($user1->id);

    $this->actingAs($user2, 'user');
    $invoice->update(['name' => 'Updated invoice']);

    expect($invoice->created_by)->toBe($user1->id)
        ->and($invoice->updated_by)->toBe($user2->id)
        ->and($invoice->updater)->toBeInstanceOf(User::class)
        ->and($invoice->updater->id)->toBe($user2->id);
});
