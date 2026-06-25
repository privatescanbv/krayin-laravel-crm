<?php

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
