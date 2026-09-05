<?php

use Surface\Contracts\NativeWindows\WindowableException;
use Surface\NativeWindows\Components\SelectButton;
use Venusian\Surface\Tests\Support\Fakes\FakeToggleButton;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

function selectButtonUnderTest(FakeWindow $window): SelectButton
{
    $group = new SelectButton($window, 'modes', 0, 0, 400, 44);
    $group->addOption('day', 'Day');
    $group->addOption('night', 'Night');
    $group->addOption('auto', 'Auto');

    return $group;
}

it('flows options left to right at natural size, centred', function () {
    $window = new FakeWindow('main');
    $group = selectButtonUnderTest($window);

    // Fake hug is 80x30; pad 4, gap 4; centred on 44 → y 7.
    expect($group->part('option.day')->frame())->toBe(['x' => 4, 'y' => 7, 'width' => 80, 'height' => 30])
        ->and($group->part('option.night')->frame())->toBe(['x' => 88, 'y' => 7, 'width' => 80, 'height' => 30])
        ->and($group->part('option.auto')->frame())->toBe(['x' => 172, 'y' => 7, 'width' => 80, 'height' => 30])
        ->and($window->view('modes.option.day'))->toBeInstanceOf(FakeToggleButton::class);
});

it('selection is exclusive: one pressed option once anything is selected', function () {
    $window = new FakeWindow('main');
    $group = selectButtonUnderTest($window);

    $group->select('night');
    $group->select('auto');

    expect($group->selectedKey())->toBe('auto')
        ->and($window->view('modes.option.auto')->isPressed())->toBeTrue()
        ->and($window->view('modes.option.night')->isPressed())->toBeFalse()
        ->and($window->view('modes.option.day')->isPressed())->toBeFalse();
});

it('a user press selects, runs the hook, and releases the previous option', function () {
    $window = new FakeWindow('main');
    $group = selectButtonUnderTest($window);
    $picked = [];
    $group->onSelect(function (string $key) use (&$picked) { $picked[] = $key; });
    $group->select('day');

    /** @var FakeToggleButton $night */
    $night = $window->view('modes.option.night');
    $night->press(true);

    expect($group->selectedKey())->toBe('night')
        ->and($picked)->toBe(['night'])
        ->and($window->view('modes.option.day')->isPressed())->toBeFalse();
});

it('programmatic select stays silent', function () {
    $window = new FakeWindow('main');
    $group = selectButtonUnderTest($window);
    $picked = [];
    $group->onSelect(function (string $key) use (&$picked) { $picked[] = $key; });

    $group->select('day');

    expect($picked)->toBe([]);
});

it('unpressing the selected option snaps it back — selection is sticky', function () {
    $window = new FakeWindow('main');
    $group = selectButtonUnderTest($window);
    $group->select('day');

    /** @var FakeToggleButton $day */
    $day = $window->view('modes.option.day');
    $day->press(false);

    expect($day->isPressed())->toBeTrue()
        ->and($group->selectedKey())->toBe('day');
});

it('refuses a duplicate option key and selecting a ghost', function () {
    $window = new FakeWindow('main');
    $group = selectButtonUnderTest($window);

    expect(fn () => $group->addOption('day', 'Again'))
        ->toThrow(WindowableException::class, "already has an option 'day'")
        ->and(fn () => $group->select('ghost'))
        ->toThrow(WindowableException::class, "no option 'ghost'");
});

it('place re-centres the flowed options in the new frame', function () {
    $window = new FakeWindow('main');
    $group = selectButtonUnderTest($window);

    $group->place(0, 0, 500, 60);

    expect($group->part('option.day')->frame())->toBe(['x' => 4, 'y' => 15, 'width' => 80, 'height' => 30])
        ->and($group->part('option.night')->frame()['x'])->toBe(88);
});

it('removal frees the root and option names', function () {
    $window = new FakeWindow('main');
    $group = selectButtonUnderTest($window);

    $group->remove();

    expect($window->view('modes'))->toBeNull()
        ->and($window->view('modes.option.day'))->toBeNull()
        ->and($window->view('modes.option.auto'))->toBeNull();
});
