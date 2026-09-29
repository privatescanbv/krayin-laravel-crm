<?php

use App\Validators\DateValidator;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;

test('date validator rejects birth years before 1900', function (string $value, bool $passes) {
    expect((new DateValidator)->passes('date_of_birth', $value))->toBe($passes);
})->with([
    'dutch two-digit year' => ['26-03-0072', false],
    'html two-digit year'  => ['0072-03-26', false],
    'dutch valid'          => ['26-03-1972', true],
    'html valid'           => ['1972-03-26', true],
]);

test('migration moves two-digit birth years into the 1900s', function () {
    $bad = Person::factory()->create();
    $good = Person::factory()->create();
    DB::table('persons')->where('id', $bad->id)->update(['date_of_birth' => '0072-03-26']);
    DB::table('persons')->where('id', $good->id)->update(['date_of_birth' => '1985-06-01']);

    (require database_path('migrations/2026_09_29_110000_fix_two_digit_birth_years.php'))->up();

    expect($bad->fresh()->date_of_birth->toDateString())->toBe('1972-03-26')
        ->and($good->fresh()->date_of_birth->toDateString())->toBe('1985-06-01');
});
