<?php

use Surface\Contracts\NativeWindows\WindowableException;
use Surface\NativeWindows\AboutInfo;
use Surface\NativeWindows\Enums\FontWeight;
use Surface\NativeWindows\Enums\TextAlignment;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeApplication;
use Venusian\Surface\Tests\Views\Fakes\FakeWindow;

function greeterInfo(): AboutInfo
{
    return new AboutInfo(
        name: 'Greeter',
        version: '1.0.0',
        copyright: '© 2026 Example',
        credits: 'Built on Venusian Surface',
    );
}

it('refuses an About without a name', function () {
    new AboutInfo(name: '  ');
})->throws(WindowableException::class, 'name');

it('remembers the info and installs the menu item once', function () {
    $log = new CallLog;
    $os = new FakeApplication($log);

    $os->about(greeterInfo());
    $os->about(new AboutInfo(name: 'Renamed'));

    expect($os->aboutInfo()?->name)->toBe('Renamed')
        ->and($log->of('aboutItem'))->toHaveCount(1)
        ->and($log->of('aboutItem')[0]['args'])->toBe(['About Greeter']);
});

it('refuses to show an About that was never registered', function () {
    (new FakeApplication(new CallLog))->showAbout();
})->throws(WindowableException::class, 'about(AboutInfo)');

it('builds the About window from the info, one centred label per field', function () {
    $log = new CallLog;
    $os = new FakeApplication($log);
    $os->about(greeterInfo());
    $log->clear();

    $window = $os->showAbout();

    $created = array_map(fn (array $entry): array => $entry['args'], $log->of('createLabel'));
    $fonts = array_map(fn (array $entry): array => $entry['args'], $log->of('setFont'));
    $root = $window->root();

    expect($window)->toBeInstanceOf(FakeWindow::class)
        ->and($window->window_name)->toBe('About Greeter')
        ->and($os->getWindow('About Greeter'))->toBe($window)
        ->and($window->starting_width)->toBe(360)
        ->and($log->ops()[0])->toBe('createWindow')
        ->and($log->ops()[1])->toBe('present')
        ->and($created)->toBe([['Greeter'], ['Version 1.0.0'], ['Built on Venusian Surface'], ['© 2026 Example']])
        ->and($fonts[0])->toBe(['', 20.0, FontWeight::BOLD])
        ->and($fonts[3])->toBe(['', 11.0, FontWeight::REGULAR])
        ->and($log->of('setAlignment'))->toHaveCount(4)
        ->and($log->of('setAlignment')[0]['args'])->toBe([TextAlignment::CENTER])
        ->and($log->of('setTextColor'))->toHaveCount(1)
        ->and($root->child('name')?->frame()->y)->toBe(24)
        ->and($root->child('version')?->frame()->y)->toBe(60)
        ->and($root->child('copyright')?->frame()->width)->toBe(312)
        ->and($window->getFromTree('copyright'))->toBe($root->child('copyright'));
});

it('leaves empty fields out and sizes the window to what is left', function () {
    $log = new CallLog;
    $os = new FakeApplication($log);
    $os->about(new AboutInfo(name: 'Bare'));

    $window = $os->showAbout();

    expect(array_keys($window->root()->children()))->toBe(['name'])
        ->and($window->starting_height)->toBe(24 + 28 + 24);
});

it('brings the open About window forward instead of building another', function () {
    $log = new CallLog;
    $os = new FakeApplication($log);
    $os->about(greeterInfo());

    $first = $os->showAbout();
    $log->clear();
    $second = $os->showAbout();

    expect($second)->toBe($first)
        ->and($log->ops())->toBe(['present']);
});

it('builds a fresh About window after the user closed the last one', function () {
    $log = new CallLog;
    $os = new FakeApplication($log);
    $os->about(greeterInfo());

    /** @var FakeWindow $first */
    $first = $os->showAbout();
    $first->simulateOsClose();
    $log->clear();

    $second = $os->showAbout();

    expect($second)->not->toBe($first)
        ->and($os->getWindow('About Greeter'))->toBe($second)
        ->and($log->ops()[0])->toBe('createWindow')
        ->and($log->of('closeWindow'))->toBe([])
        ->and($second->root()->child('name')?->isAlive())->toBeTrue();
});
