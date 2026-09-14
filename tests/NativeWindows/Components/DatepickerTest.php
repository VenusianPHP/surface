<?php

use Surface\Contracts\NativeWindows\WindowableException;
use Surface\NativeWindows\Components\Datepicker;
use Venusian\Surface\Tests\Support\Fakes\FakeButton;
use Venusian\Surface\Tests\Support\Fakes\FakeDatePicker;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeTextInput;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('mounts a field and a trigger, and no calendar until asked', function () {
    $window = new FakeWindow('main');

    $picker = new Datepicker($window, 'when', 10, 20, 200, 30, date: '2026-09-12');

    expect($window->view('when'))->toBeInstanceOf(FakeGroup::class)
        ->and($window->view('when.input'))->toBeInstanceOf(FakeTextInput::class)
        ->and($window->view('when.trigger'))->toBeInstanceOf(FakeButton::class)
        ->and($window->view('when.calendar'))->toBeNull()
        ->and($picker->isOpen())->toBeFalse()
        ->and($picker->part('input')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 170, 'height' => 30])
        ->and($picker->part('trigger')->frame())->toBe(['x' => 170, 'y' => 0, 'width' => 30, 'height' => 30])
        ->and($picker->input()->value())->toBe('2026-09-12')
        ->and($picker->date())->toBe('2026-09-12')
        ->and($picker->year())->toBe(2026)
        ->and($picker->month())->toBe(9)
        ->and($picker->day())->toBe(12);
});

it('starts empty with the placeholder when no date is given', function () {
    $window = new FakeWindow('main');

    $picker = new Datepicker($window, 'when', 0, 0, 200, 30);

    expect($picker->date())->toBeNull()
        ->and($picker->input()->value())->toBe('')
        ->and($picker->input()->placeholder())->toBe('YYYY-MM-DD');
});

it('opens the calendar under the field, seeded with the date, and closes it again', function () {
    $window = new FakeWindow('main');
    $panel = $window->group('panel', 0, 0, 800, 600);
    $picker = new Datepicker($window, 'when', 10, 20, 200, 30, date: '2026-09-12', in: $panel);

    /** @var FakeButton $trigger */
    $trigger = $picker->part('trigger');
    $trigger->click();

    $calendar = $window->view('when.calendar');
    expect($picker->isOpen())->toBeTrue()
        ->and($calendar)->toBeInstanceOf(FakeDatePicker::class)
        ->and($picker->part('calendar'))->toBe($calendar)
        ->and($calendar->frame())->toBe(['x' => 10, 'y' => 52, 'width' => 280, 'height' => 190])
        ->and($calendar->date())->toBe('2026-09-12')
        ->and($panel->children())->toContain($calendar);

    $trigger->click();

    expect($picker->isOpen())->toBeFalse()
        ->and($window->view('when.calendar'))->toBeNull()
        ->and($picker->part('calendar'))->toBeNull()
        ->and($picker->date())->toBe('2026-09-12');
});

it('resolves the field, collapses the calendar, and fires onChange when a day is picked', function () {
    $window = new FakeWindow('main');
    $picker = new Datepicker($window, 'when', 0, 0, 200, 30, date: '2026-01-01');
    $seen = [];
    $picker->onChange(function (int $y, int $m, int $d) use (&$seen) { $seen[] = [$y, $m, $d]; });

    $picker->open();
    /** @var FakeDatePicker $calendar */
    $calendar = $picker->part('calendar');
    $calendar->pickDate(2026, 3, 14);

    expect($seen)->toBe([[2026, 3, 14]])
        ->and($picker->date())->toBe('2026-03-14')
        ->and($picker->input()->value())->toBe('2026-03-14')
        ->and($picker->isOpen())->toBeFalse()
        ->and($window->view('when.calendar'))->toBeNull();
});

it('resolves a typed Y-m-d and ignores partial text', function () {
    $window = new FakeWindow('main');
    $picker = new Datepicker($window, 'when', 0, 0, 200, 30, date: '2026-01-01');
    $seen = [];
    $picker->onChange(function (int $y, int $m, int $d) use (&$seen) { $seen[] = [$y, $m, $d]; });

    /** @var FakeTextInput $input */
    $input = $picker->part('input');
    $input->typeText('2026-0');
    $input->typeText('2026-02-30');

    expect($seen)->toBe([])
        ->and($picker->date())->toBe('2026-01-01');

    $picker->open();
    $input->typeText('2026-07-04');

    expect($seen)->toBe([[2026, 7, 4]])
        ->and($picker->date())->toBe('2026-07-04')
        ->and($picker->isOpen())->toBeTrue()
        ->and($picker->part('calendar')->date())->toBe('2026-07-04');
});

it('setDate is silent, writes the field, and seeds an open calendar', function () {
    $window = new FakeWindow('main');
    $picker = new Datepicker($window, 'when', 0, 0, 200, 30, date: '2026-01-01');
    $seen = [];
    $picker->onChange(function (int $y, int $m, int $d) use (&$seen) { $seen[] = [$y, $m, $d]; });

    $picker->open();
    $picker->setDate('2026-09-12');

    expect($seen)->toBe([])
        ->and($picker->date())->toBe('2026-09-12')
        ->and($picker->input()->value())->toBe('2026-09-12')
        ->and($picker->part('calendar')->date())->toBe('2026-09-12');

    $picker->setDate(null);

    expect($picker->date())->toBeNull()
        ->and($picker->input()->value())->toBe('');
});

it('rejects a date that is not Y-m-d', function () {
    $window = new FakeWindow('main');
    $picker = new Datepicker($window, 'when', 0, 0, 200, 30);

    $picker->setDate('12/09/2026');
})->throws(WindowableException::class);

it('place re-lays the field and follows with an open calendar', function () {
    $window = new FakeWindow('main');
    $picker = new Datepicker($window, 'when', 0, 0, 200, 30, date: '2026-09-12');
    $picker->open();

    $picker->place(40, 60, 240, 28);

    expect($picker->part('input')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 212, 'height' => 28])
        ->and($picker->part('trigger')->frame())->toBe(['x' => 212, 'y' => 0, 'width' => 28, 'height' => 28])
        ->and($picker->part('calendar')->frame())->toBe(['x' => 40, 'y' => 90, 'width' => 280, 'height' => 190]);
});

it('hiding or disabling collapses an open calendar', function () {
    $window = new FakeWindow('main');
    $picker = new Datepicker($window, 'when', 0, 0, 200, 30, date: '2026-09-12');

    $picker->open();
    $picker->hide();
    expect($picker->isOpen())->toBeFalse();

    $picker->show();
    $picker->open();
    $picker->setEnabled(false);
    expect($picker->isOpen())->toBeFalse()
        ->and($picker->input()->isEnabled())->toBeFalse()
        ->and($picker->part('trigger')->isEnabled())->toBeFalse();

    $picker->open();
    expect($picker->isOpen())->toBeFalse();
});

it('removal frees the root, the parts, and an open calendar', function () {
    $window = new FakeWindow('main');
    $picker = new Datepicker($window, 'when', 0, 0, 200, 30, date: '2026-09-12');
    $picker->open();

    $picker->remove();

    expect($window->view('when'))->toBeNull()
        ->and($window->view('when.input'))->toBeNull()
        ->and($window->view('when.trigger'))->toBeNull()
        ->and($window->view('when.calendar'))->toBeNull()
        ->and($picker->part('calendar'))->toBeNull()
        ->and($picker->isOpen())->toBeFalse();
});
