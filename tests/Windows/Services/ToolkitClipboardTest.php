<?php

declare(strict_types=1);

use Surface\Contracts\Windows\Mail\Services\ClipboardChanged;
use Surface\Contracts\Windows\Mail\Services\ClipboardText;
use Venusian\Surface\Tests\Fixtures\FakeWindowDriver;

it('gives each text request its own id and posts nothing until it is answered', function (): void {
    $driver = new FakeWindowDriver();
    $first = $driver->clipboard()->requestText();
    $second = $driver->clipboard()->requestText();

    expect($first)->toMatch('/^[0-9a-f]{16}$/')
        ->and($second)->not->toBe($first)
        ->and($driver->clipboard()->asked)->toBe([$first, $second])
        ->and($driver->mail)->toBe([]);
});

it('posts one ClipboardText for each request id', function (): void {
    $driver = new FakeWindowDriver();
    $first = $driver->clipboard()->requestText();
    $second = $driver->clipboard()->requestText();
    $driver->clipboard()->answer($second, null);
    $driver->clipboard()->answer($first, 'copied');
    $driver->clipboard()->answer($first, 'again');

    expect($driver->mail)->toEqual([new ClipboardText($second, null), new ClipboardText($first, 'copied')]);
});

it('names the clipboard mail', function (): void {
    expect((new ClipboardText('abc', 'x'))->name())->toBe('clipboard.text.abc')
        ->and((new ClipboardChanged())->name())->toBe('clipboard.changed');
});

it('says whether the clipboard holds text', function (): void {
    $driver = new FakeWindowDriver();
    expect($driver->clipboard()->hasText())->toBeFalse();

    $driver->clipboard()->setText('x');
    expect($driver->clipboard()->hasText())->toBeTrue();
});
