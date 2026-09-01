<?php

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use PDOException;

test('lost-connection PDOExceptions from workers are not reported', function () {
    $handler = app(ExceptionHandler::class);

    expect($handler->shouldReport(
        new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away')
    ))->toBeFalse()
        ->and($handler->shouldReport(
            new PDOException('SQLSTATE[23000]: Integrity constraint violation')
        ))->toBeTrue();

    // Real DB errors still get reported.
});
