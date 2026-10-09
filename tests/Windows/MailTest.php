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
    'focus lost' => [new \Surface\Contracts\Windows\Mail\WindowFocusLost('main'), 'window.focus-lost.main'],
    'occluded' => [new \Surface\Contracts\Windows\Mail\WindowOccluded('main'), 'window.occluded.main'],
    'exposed' => [new \Surface\Contracts\Windows\Mail\WindowExposed('main'), 'window.exposed.main'],
    'close requested' => [new \Surface\Contracts\Windows\Mail\WindowCloseRequested('main'), 'window.close-requested.main'],
    'moved' => [new \Surface\Contracts\Windows\Mail\WindowMoved('main', 1, 2), 'window.moved.main'],
    'mode changed' => [new \Surface\Contracts\Windows\Mail\WindowModeChanged('main', \Surface\Contracts\Windows\WindowMode::Maximized), 'window.mode-changed.main'],
    'display changed' => [new \Surface\Contracts\Windows\Mail\WindowDisplayChanged('main', 2), 'window.display-changed.main'],
    'scale changed' => [new \Surface\Contracts\Windows\Mail\WindowScaleChanged('main', 2.0), 'window.scale-changed.main'],
    'frame due' => [new \Surface\Contracts\Windows\Mail\WindowFrameDue('main', 1.0, 1.016), 'window.frame-due.main'],
    'displays changed' => [new \Surface\Contracts\Windows\Mail\DisplaysChanged(), 'displays.changed'],
]);

it('carries the window and item', function (): void {
    $toggled = new MenuToggled('main', 'grid', true);

    expect($toggled)->toBeInstanceOf(WindowMail::class)
        ->and($toggled->window())->toBe('main')
        ->and($toggled->item)->toBe('grid')
        ->and($toggled->on)->toBeTrue()
        ->and((new QuitRequested())->window)->toBeNull();
});
