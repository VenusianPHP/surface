<?php

use Surface\Contracts\NativeWindows\Events\View\RowSelected;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeTable;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('conjures a table with headers, rows, and no selection, and places it at once', function () {
    $window = new FakeWindow('main');

    $table = $window->table('list', ['Name', 'Id'], [['Io', '1'], ['Titan', '2']], 10, 20, 400, 200);

    expect($table)->toBeInstanceOf(FakeTable::class)
        ->and($table->columns())->toBe(['Name', 'Id'])
        ->and($table->rows())->toBe([['Io', '1'], ['Titan', '2']])
        ->and($table->selectedRow())->toBe(-1)
        ->and($table->selectedCells())->toBeNull()
        ->and($table->applied_frames)->toBe([[10, 20, 400, 200]]);
});

it('an empty table answers -1 and null', function () {
    $window = new FakeWindow('main');
    $table = $window->table('list', ['Name'], [], 0, 0, 400, 200);

    expect($table->selectedRow())->toBe(-1)
        ->and($table->selectedCells())->toBeNull();
});

it('setRows replaces the data wholesale, writes through, and clears the selection', function () {
    $window = new FakeWindow('main');
    $table = $window->table('list', ['Name'], [['Io']], 0, 0, 400, 200);
    $table->selectRow(0);

    $table->setRows([['Titan'], ['Europa']]);

    expect($table->rows())->toBe([['Titan'], ['Europa']])
        ->and($table->selectedRow())->toBe(-1)
        ->and($table->applied_rows)->toBe([[['Titan'], ['Europa']]])
        ->and($table->applied_selected)->toBe([0, -1]);
});

it('selectRow is silent and clamps into the rows', function () {
    $window = new FakeWindow('main');
    $table = $window->table('list', ['Name'], [['Io'], ['Titan']], 0, 0, 400, 200);
    $seen = [];
    $table->onSelect(function (int $row, array $cells) use (&$seen) { $seen[] = [$row, $cells]; });

    $table->selectRow(9);

    expect($table->selectedRow())->toBe(1)
        ->and($table->selectedCells())->toBe(['Titan'])
        ->and($table->applied_selected)->toBe([1])
        ->and($seen)->toBe([]);

    $table->selectRow(-3);

    expect($table->selectedRow())->toBe(0)
        ->and($table->applied_selected)->toBe([1, 0]);
});

it('an engine pick updates the row, invokes the hook, and rides the dock', function () {
    $dock = bareDock();
    $window = new FakeWindow('main');
    $window->setPool($dock);
    $seen = [];
    $table = $window->table('list', ['Name', 'Id'], [['Io', '1'], ['Titan', '2']], 0, 0, 400, 200)
        ->onSelect(function (int $row, array $cells) use (&$seen) { $seen[] = [$row, $cells]; });

    $table->pickRow(1);

    $mail = $dock->drain()->first(fn (object $mail) => $mail instanceof RowSelected);
    expect($table->selectedRow())->toBe(1)
        ->and($table->selectedCells())->toBe(['Titan', '2'])
        ->and($seen)->toBe([[1, ['Titan', '2']]])
        ->and($mail->name)->toBe('main.list.selected')
        ->and($mail->row)->toBe(1)
        ->and($mail->cells)->toBe(['Titan', '2']);
});

it('a pick on an empty table stays at -1 and still rides the dock', function () {
    $dock = bareDock();
    $window = new FakeWindow('main');
    $window->setPool($dock);
    $table = $window->table('list', ['Name'], [], 0, 0, 400, 200);

    $table->pickRow(0);

    $mail = $dock->drain()->first(fn (object $mail) => $mail instanceof RowSelected);
    expect($table->selectedRow())->toBe(-1)
        ->and($table->selectedCells())->toBeNull()
        ->and($mail->row)->toBe(-1)
        ->and($mail->cells)->toBe([]);
});

it('group sugar conjures the table into the host', function () {
    $window = new FakeWindow('main');
    $group = $window->group('card', 0, 0, 400, 200);

    $table = $group->table('list', ['Name'], [['Io']], 0, 0, 400, 180);

    expect($table)->toBeInstanceOf(FakeTable::class)
        ->and($window->view('list'))->toBe($table)
        ->and($table->hostedBy())->toBeInstanceOf(FakeGroup::class);
});
