<?php

use Surface\NativeWindows\Components\DataTable;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeTable;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('mounts a table at the component name path filling the root', function () {
    $window = new FakeWindow('main');

    $table = new DataTable($window, 'list', 10, 20, 400, 200, columns: ['Name'], rows: [['Io']]);

    expect($window->view('list'))->toBeInstanceOf(FakeGroup::class)
        ->and($window->view('list.table'))->toBeInstanceOf(FakeTable::class)
        ->and($table->part('table')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 400, 'height' => 200])
        ->and($table->columns())->toBe(['Name'])
        ->and($table->rows())->toBe([['Io']])
        ->and($table->selectedRow())->toBe(-1);
});

it('delegates rows and selection reads and writes to the inner table', function () {
    $window = new FakeWindow('main');
    $table = new DataTable($window, 'list', 0, 0, 400, 200, columns: ['Name'], rows: [['Io']]);

    $table->setRows([['Titan'], ['Europa']]);

    expect($table->rows())->toBe([['Titan'], ['Europa']])
        ->and($table->part('table')->rows())->toBe([['Titan'], ['Europa']]);

    $table->selectRow(1);

    expect($table->selectedRow())->toBe(1)
        ->and($table->selectedCells())->toBe(['Europa']);
});

it('fires onSelect from the engine door, not selectRow', function () {
    $window = new FakeWindow('main');
    $table = new DataTable($window, 'list', 0, 0, 400, 200, columns: ['Name'], rows: [['Io'], ['Titan']]);
    $seen = [];
    $table->onSelect(function (int $row, array $cells) use (&$seen) { $seen[] = [$row, $cells]; });

    $table->selectRow(1);

    expect($seen)->toBe([])
        ->and($table->selectedCells())->toBe(['Titan']);

    /** @var FakeTable $inner */
    $inner = $table->part('table');
    $inner->pickRow(0);

    expect($seen)->toBe([[0, ['Io']]])
        ->and($table->selectedRow())->toBe(0)
        ->and($table->selectedCells())->toBe(['Io']);
});

it('place stretches the inner table to the new inner size', function () {
    $window = new FakeWindow('main');
    $table = new DataTable($window, 'list', 0, 0, 400, 200, columns: ['Name'], rows: [['Io']]);

    $table->place(0, 0, 480, 240);

    expect($table->part('table')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 480, 'height' => 240]);
});

it('removal frees the root and part names', function () {
    $window = new FakeWindow('main');
    $table = new DataTable($window, 'list', 0, 0, 400, 200, columns: ['Name'], rows: [['Io']]);

    $table->remove();

    expect($window->view('list'))->toBeNull()
        ->and($window->view('list.table'))->toBeNull()
        ->and($table->part('table'))->toBeNull();
});
