<?php

use Surface\Contracts\NativeWindows\Events\View\TextChanged;
use Venusian\Surface\Tests\Support\Fakes\FakeTextArea;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('conjures a text area, registers it by name and places it at once', function () {
    $window = new FakeWindow('main');

    $area = $window->textArea('notes', "line one\n", 10, 20, 300, 200);

    expect($area)->toBeInstanceOf(FakeTextArea::class)
        ->and($area->value())->toBe("line one\n")
        ->and($area->isEditable())->toBeTrue()
        ->and($window->view('notes'))->toBe($area)
        ->and($area->applied_frames)->toBe([[10, 20, 300, 200]]);
});

it('writes value and editable through to the engine, editable change-only', function () {
    $window = new FakeWindow('main');
    $area = $window->textArea('notes', '', 0, 0, 300, 200);

    $area->setValue('draft');
    $area->setEditable(false);
    $area->setEditable(false);
    $area->setEditable(true);

    expect($area->applied_values)->toBe(['draft'])
        ->and($area->applied_editable)->toBe([false, true]);
});

it('an engine edit updates the value, invokes the hook, and rides the dock', function () {
    $dock = bareDock();
    $window = new FakeWindow('main');
    $window->setPool($dock);
    $seen = [];
    $area = $window->textArea('notes', '', 0, 0, 300, 200)
        ->onChange(function (string $value) use (&$seen) { $seen[] = $value; });

    $area->edit('hello');

    $mail = $dock->drain()->first(fn (object $mail) => $mail instanceof TextChanged);
    expect($area->value())->toBe('hello')
        ->and($seen)->toBe(['hello'])
        ->and($mail->name)->toBe('main.notes.changed')
        ->and($mail->value)->toBe('hello');
});
