<?php

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Admin\DataGrids\Contact\PersonDataGrid;
use Webkul\DataGrid\ColumnTypes\Date;

uses(RefreshDatabase::class);

test('birth date column uses manual date range filter without quick presets', function () {
    $dataGrid = app(PersonDataGrid::class);
    $dataGrid->prepareColumns();

    $birthDateColumn = collect($dataGrid->getColumns())
        ->first(fn ($column) => $column->getIndex() === 'date_of_birth');

    expect($birthDateColumn)->toBeInstanceOf(Date::class)
        ->and($birthDateColumn->getFilterableType())->toBe('date_range')
        ->and($birthDateColumn->getDateRangeQuickFilters())->toBeFalse()
        ->and($birthDateColumn->getFilterableOptions())->toBe([])
        ->and($birthDateColumn->toArray()['date_range_quick_filters'])->toBeFalse();
});

test('birth date column formats date as d-m-Y with age', function () {
    $dataGrid = app(PersonDataGrid::class);
    $dataGrid->prepareColumns();

    $col = collect($dataGrid->getColumns())
        ->first(fn ($c) => $c->getIndex() === 'date_of_birth');

    $closure = $col->getClosure();

    $row = (object) ['date_of_birth' => '1985-03-15'];
    $age = Carbon::parse('1985-03-15')->age;

    expect($closure($row))->toBe('15-03-1985 ('.$age.' jaar)');
});

test('birth date column returns dash when date_of_birth is null', function () {
    $dataGrid = app(PersonDataGrid::class);
    $dataGrid->prepareColumns();

    $col = collect($dataGrid->getColumns())
        ->first(fn ($c) => $c->getIndex() === 'date_of_birth');

    $closure = $col->getClosure();

    expect($closure((object) ['date_of_birth' => null]))->toBe('-');
});

test('date_of_birth filter auto-fills to when only from is provided', function () {
    request()->merge(['filters' => ['date_of_birth' => [['2008-05-16', '']]]]);

    $dataGrid = app(PersonDataGrid::class);
    $dataGrid->prepareQueryBuilder();

    $filters = request()->input('filters');
    expect($filters['date_of_birth'][0][0])->toBe('2008-05-16')
        ->and($filters['date_of_birth'][0][1])->toBe('2008-05-16');
});

test('date_of_birth filter auto-fills from when only to is provided', function () {
    request()->merge(['filters' => ['date_of_birth' => [['', '2008-05-16']]]]);

    $dataGrid = app(PersonDataGrid::class);
    $dataGrid->prepareQueryBuilder();

    $filters = request()->input('filters');
    expect($filters['date_of_birth'][0][0])->toBe('2008-05-16')
        ->and($filters['date_of_birth'][0][1])->toBe('2008-05-16');
});

test('date_of_birth filter leaves range unchanged when both from and to are set', function () {
    request()->merge(['filters' => ['date_of_birth' => [['2008-05-01', '2008-05-31']]]]);

    $dataGrid = app(PersonDataGrid::class);
    $dataGrid->prepareQueryBuilder();

    $filters = request()->input('filters');
    expect($filters['date_of_birth'][0][0])->toBe('2008-05-01')
        ->and($filters['date_of_birth'][0][1])->toBe('2008-05-31');
});

test('Date column processFilter uses midnight (00:00:00) as start of day', function () {
    $col = new Date([
        'index'      => 'date_of_birth',
        'label'      => 'Geboortedatum',
        'type'       => 'date',
        'sortable'   => false,
        'filterable' => true,
    ]);

    $capturedBindings = null;

    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('where')->andReturnUsing(function ($callback) use ($mockBuilder, &$capturedBindings) {
        $innerBuilder = Mockery::mock(Builder::class);
        $innerBuilder->shouldReceive('whereBetween')->andReturnUsing(function ($col, $range) use (&$capturedBindings, $innerBuilder) {
            $capturedBindings = $range;

            return $innerBuilder;
        });
        $callback($innerBuilder);

        return $mockBuilder;
    });

    $col->processFilter($mockBuilder, [['2008-05-16', '2008-05-16']]);

    expect($capturedBindings[0])->toBe('2008-05-16 00:00:00')
        ->and($capturedBindings[1])->toBe('2008-05-16 23:59:59');
});
