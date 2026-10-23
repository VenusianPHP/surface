<?php

declare(strict_types=1);

use Surface\Contracts\Windows\Mail\Services\AlertAnswered;
use Surface\Contracts\Windows\Mail\Services\FilesChosen;
use Surface\Contracts\Windows\Mail\WindowClosed;
use Surface\Contracts\Windows\Services\Alert;
use Surface\Contracts\Windows\Services\OpenRequest;
use Surface\Contracts\Windows\Services\SaveRequest;
use Surface\Contracts\Windows\WindowException;
use Surface\Windows\Services\ToolkitDialogs;
use Venusian\Surface\Tests\Fixtures\FakeWindowDriver;

/** Each mail as [class, name]. */
function mailOf(FakeWindowDriver $driver): array
{
    return array_map(fn (object $mail): array => [$mail::class, $mail->name()], $driver->mail);
}

it('returns a request id and posts nothing when it shows an alert', function (): void {
    $driver = new FakeWindowDriver();
    $main = $driver->open('main', 400, 300);
    $other = $driver->open('other', 400, 300);
    $id = $driver->dialogs()->alert($main, new Alert('Hi'));

    expect($id)->toMatch('/^[0-9a-f]{16}$/')
        ->and($driver->dialogs()->alert($other, new Alert('Hi')))->not->toBe($id)
        ->and($driver->mail)->toBe([])
        ->and($driver->dialogs()->openOn($main))->toBe($id)
        ->and($driver->dialogs()->shown[0])->toBe(['alert', $id, 'main']);
});

it('posts one answer for a request id', function (): void {
    $driver = new FakeWindowDriver();
    $main = $driver->open('main', 400, 300);
    $id = $driver->dialogs()->alert($main, new Alert('Hi', null, ['A', 'B']));
    $driver->dialogs()->answer($id, 1);
    $driver->dialogs()->answer($id, 0);

    expect($driver->mail)->toEqual([new AlertAnswered($id, 1)])
        ->and($driver->mail[0]->name())->toBe("dialog.alert.{$id}")
        ->and($driver->dialogs()->openOn($main))->toBeNull();
});

it('posts the chosen paths of a file panel', function (string $kind): void {
    $driver = new FakeWindowDriver();
    $main = $driver->open('main', 400, 300);
    $id = $kind === 'open'
        ? $driver->dialogs()->openFiles($main, new OpenRequest(multiple: true))
        : $driver->dialogs()->saveFile($main, new SaveRequest(name: 'a.txt'));
    $driver->dialogs()->answer($id, ['/a', '/b']);

    expect($driver->mail)->toEqual([new FilesChosen($id, ['/a', '/b'])])
        ->and($driver->mail[0]->name())->toBe("dialog.files.{$id}")
        ->and($driver->dialogs()->shown[0][0])->toBe($kind);
})->with(['open', 'save']);

it('answers a chosen folder without its trailing slash, as every toolkit does', function (): void {
    $driver = new FakeWindowDriver();
    $main = $driver->open('main', 400, 300);
    $id = $driver->dialogs()->openFiles($main, new OpenRequest(folders: true));
    $driver->dialogs()->answer($id, ['/tmp/files/']);
    $root = $driver->dialogs()->openFiles($main, new OpenRequest(folders: true));
    $driver->dialogs()->answer($root, ['/']);

    expect($driver->mail)->toEqual([new FilesChosen($id, ['/tmp/files']), new FilesChosen($root, ['/'])]);
});

it('answers the open dialog before the parent closes, and only once', function (string $kind, int|array $answer): void {
    $driver = new FakeWindowDriver();
    $main = $driver->open('main', 400, 300);
    $id = $kind === 'alert' ? $driver->dialogs()->alert($main, new Alert('Hi')) : $driver->dialogs()->openFiles($main, new OpenRequest());
    $main->close();
    $driver->dialogs()->answer($id, $kind === 'alert' ? 0 : ['/late']);

    $first = $kind === 'alert' ? new AlertAnswered($id, -1) : new FilesChosen($id, []);
    expect($driver->mail)->toEqual([$first, new WindowClosed('main')])
        ->and($driver->dialogs()->dismissed)->toBe([$id]);
})->with([['alert', -1], ['files', []]]);

it('answers the dialog of a child before the child and its parent close', function (): void {
    $driver = new FakeWindowDriver();
    $main = $driver->open('main', 400, 300);
    $note = $driver->open('note', 300, 120, null, $main);
    $id = $driver->dialogs()->alert($note, new Alert('Hi'));
    $main->close();

    expect($driver->mail)->toEqual([new AlertAnswered($id, -1), new WindowClosed('note'), new WindowClosed('main')]);
});

it('refuses a parent that cannot take a dialog', function (string $case): void {
    $driver = new FakeWindowDriver();
    $main = $driver->open('main', 400, 300);
    $parent = $main;
    $call = 'alert';
    if ($case === 'open alert') {
        $driver->dialogs()->alert($main, new Alert('First'));
    } elseif ($case === 'files on open alert') {
        $driver->dialogs()->alert($main, new Alert('First'));
        $call = 'files';
    } elseif ($case === 'child on open alert') {
        $driver->dialogs()->alert($main, new Alert('First'));
        $call = 'child';
    } elseif ($case === 'open child') {
        $driver->open('child', 300, 120, null, $main);
    } elseif ($case === 'closed') {
        $main->close();
    } elseif ($case === 'other driver') {
        $parent = (new FakeWindowDriver())->open('main', 400, 300);
    }

    $show = match ($call) {
        'alert' => fn () => $driver->dialogs()->alert($parent, new Alert('Hi')),
        'files' => fn () => $driver->dialogs()->openFiles($parent, new OpenRequest()),
        'child' => fn () => $driver->open('child', 300, 120, null, $parent),
    };

    expect($show)->toThrow(WindowException::class, "'main'");
})->with(['open alert', 'files on open alert', 'child on open alert', 'open child', 'closed', 'other driver']);

it('takes a new dialog and a new child after the answer', function (): void {
    $driver = new FakeWindowDriver();
    $main = $driver->open('main', 400, 300);
    $driver->dialogs()->answer($driver->dialogs()->alert($main, new Alert('Hi')), 0);
    $driver->dialogs()->answer($driver->dialogs()->alert($main, new Alert('Again')), 0);
    $child = $driver->open('child', 300, 120, null, $main);

    expect($child->parent())->toBe($main);
});

it('gives the native a folder only', function (): void {
    expect(ToolkitDialogs::folder(__DIR__ . '/..'))->toBe(realpath(__DIR__ . '/..'))
        ->and(ToolkitDialogs::folder(__FILE__))->toBeNull()
        ->and(ToolkitDialogs::folder('/nonexistent-c7'))->toBeNull()
        ->and(ToolkitDialogs::folder(null))->toBeNull();
});
