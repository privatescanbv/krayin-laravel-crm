<?php

use App\Enums\ActivityType;
use App\Models\Order;
use Illuminate\Support\Facades\Storage;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Models\File as ActivityFile;
use Webkul\User\Models\User;

beforeEach(function () {
    config(['filesystems.default' => 'public']);

    Storage::fake('public');
    Storage::fake('local');

    User::factory()->create();
    actingAsSanctumAuthenticatedAdmin();
});

it('does not store missing confirmation PDFs during dry run', function () {
    [$file] = createMissingConfirmationPdfFile();

    $this->artisan('activities:repair-confirmation-pdfs', [
        '--dry-run' => true,
        '--limit'   => 10,
    ])
        ->assertSuccessful();

    Storage::disk('public')->assertMissing($file->path);
});

it('regenerates a missing confirmation PDF', function () {
    [$file] = createMissingConfirmationPdfFile([
        'confirmation_letter_content' => '<html><body><h1>Order Bevestiging</h1><p>Test</p></body></html>',
    ]);

    $this->artisan('activities:repair-confirmation-pdfs', [
        '--limit' => 10,
    ])
        ->assertSuccessful();

    Storage::disk('public')->assertExists($file->path);
    expect(Storage::disk('public')->get($file->path))
        ->toStartWith('%PDF');
});

/**
 * @return array{ActivityFile, Activity, Order}
 */
function createMissingConfirmationPdfFile(array $orderAttributes = []): array
{
    $order = Order::factory()->create(array_merge([
        'confirmation_letter_content' => '<html><body><h1>Order Bevestiging</h1></body></html>',
    ], $orderAttributes));

    $activity = Activity::query()->create([
        'title'      => 'Orderbevestiging PDF',
        'type'       => ActivityType::FILE,
        'comment'    => 'Automatisch gegenereerde orderbevestiging',
        'is_done'    => true,
        'order_id'   => $order->id,
        'additional' => [
            'document_type' => 'order_confirmation',
        ],
    ]);

    $fileName = 'order-bevestiging-'.$order->id.'-'.now()->format('Y-m-d').'.pdf';
    $file = ActivityFile::query()->create([
        'name'        => $fileName,
        'path'        => 'activities/'.$activity->id.'/'.$fileName,
        'activity_id' => $activity->id,
    ]);

    return [$file, $activity, $order];
}
