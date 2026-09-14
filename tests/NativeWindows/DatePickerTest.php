<?php

use Surface\Contracts\NativeWindows\Events\View\DateChanged;
use Surface\Contracts\NativeWindows\WindowableException;
use Venusian\Surface\Tests\Support\Fakes\FakeDatePicker;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('conjures a date picker with a day and places it at once', function () {
    $window = new FakeWindow('main');

    $picker = $window->datePicker('when', '2026-09-12', 10, 20, 200, 180);

    expect($picker)->toBeInstanceOf(FakeDatePicker::class)
        ->and($picker->date())->toBe('2026-09-12')
        ->and($picker->year())->toBe(2026)
        ->and($picker->month())->toBe(9)
        ->and($picker->day())->toBe(12)
        ->and($picker->applied_frames)->toBe([[10, 20, 200, 180]]);
});

it('a null date stays null until the engine or a setter writes one', function () {
    $window = new FakeWindow('main');
    $picker = $window->datePicker('when', null, 0, 0, 200, 180);

    expect($picker->date())->toBeNull()
        ->and($picker->year())->toBeNull()
        ->and($picker->month())->toBeNull()
        ->and($picker->day())->toBeNull();
});

it('setDate writes the day through and stays silent', function () {
    $window = new FakeWindow('main');
    $picker = $window->datePicker('when', '2026-01-01', 0, 0, 200, 180);
    $seen = [];
    $picker->onChange(function (int $y, int $m, int $d) use (&$seen) { $seen[] = [$y, $m, $d]; });

    $picker->setDate('2026-09-12');

    expect($picker->date())->toBe('2026-09-12')
        ->and($picker->applied_dates)->toBe([[2026, 9, 12]])
        ->and($seen)->toBe([]);
});

it('rejects a string that is not a calendar day', function () {
    $window = new FakeWindow('main');

    expect(fn () => $window->datePicker('when', '2026-13-40', 0, 0, 200, 180))
        ->toThrow(WindowableException::class, 'Y-m-d');
});

it('an engine pick updates the day, invokes the hook, and rides the dock', function () {
    $dock = bareDock();
    $window = new FakeWindow('main');
    $window->setPool($dock);
    $seen = [];
    $picker = $window->datePicker('when', '2026-01-01', 0, 0, 200, 180)
        ->onChange(function (int $y, int $m, int $d) use (&$seen) { $seen[] = [$y, $m, $d]; });

    $picker->pickDate(2026, 9, 12);

    $mail = $dock->drain()->first(fn (object $mail) => $mail instanceof DateChanged);
    expect($picker->date())->toBe('2026-09-12')
        ->and($seen)->toBe([[2026, 9, 12]])
        ->and($mail->name)->toBe('main.when.changed')
        ->and($mail->year)->toBe(2026)
        ->and($mail->month)->toBe(9)
        ->and($mail->day)->toBe(12)
        ->and($mail->date)->toBe('2026-09-12');
});

it('group sugar conjures the picker into the host', function () {
    $window = new FakeWindow('main');
    $group = $window->group('card', 0, 0, 240, 200);

    $picker = $group->datePicker('when', '2026-09-12', 8, 8, 220, 180);

    expect($picker)->toBeInstanceOf(FakeDatePicker::class)
        ->and($window->view('when'))->toBe($picker)
        ->and($picker->hostedBy())->toBeInstanceOf(FakeGroup::class);
});
