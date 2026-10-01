<?php

declare(strict_types=1);

use Surface\Contracts\Windows\Mail\MenuActivated;
use Surface\Contracts\Windows\Mail\MenuToggled;
use Surface\Contracts\Windows\Mail\QuitRequested;
use Surface\Contracts\Windows\Mail\WindowClosed;
use Surface\Contracts\Windows\Mail\WindowFocused;
use Surface\Contracts\Windows\Mail\WindowMail;
use Voyager\Contracts\Signals\NamedSignal;

it('names each mail so listeners can match it by name', function (object $mail, string $name): void {
    expect($mail)->toBeInstanceOf(NamedSignal::class)
        ->and($mail->name())->toBe($name);
})->with([
    'closed' => [new WindowClosed('main'), 'window.closed.main'],
    'focused' => [new WindowFocused('main'), 'window.focused.main'],
    'activated' => [new MenuActivated('main', 'view.refresh'), 'menu.activated.main.view.refresh'],
    'toggled' => [new MenuToggled('main', 'grid', true), 'menu.toggled.main.grid'],
    'quit' => [new QuitRequested('main'), 'quit.requested'],
]);

it('carries the window and item', function (): void {
    $toggled = new MenuToggled('main', 'grid', true);

    expect($toggled)->toBeInstanceOf(WindowMail::class)
        ->and($toggled->window())->toBe('main')
        ->and($toggled->item)->toBe('grid')
        ->and($toggled->on)->toBeTrue()
        ->and((new QuitRequested())->window)->toBeNull();
});
