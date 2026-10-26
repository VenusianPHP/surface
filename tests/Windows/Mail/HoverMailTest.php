<?php

declare(strict_types=1);

use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\Windows\Mail\View\CanvasEntered;
use Surface\Contracts\Windows\Mail\View\CanvasLeft;
use Surface\Contracts\Windows\Mail\View\CanvasMoved;
use Surface\Contracts\Windows\Mail\View\CanvasWheeled;
use Surface\Contracts\Windows\Mail\View\PrimitiveMail;
use Surface\Contracts\Windows\Mail\View\ScrollChanged;
use Surface\Contracts\Windows\Mail\View\ScrollPhase;

it('names hover and scroll mail by window and path', function (Closure $mail, string $name): void {
    expect($mail()->name())->toBe($name);
})->with([
    'scroll changed' => [fn (): PrimitiveMail => new ScrollChanged('main', 'main.diff.bar', 'u1', 40), 'view.scroll-changed.main.main.diff.bar'],
    'canvas moved' => [fn (): PrimitiveMail => new CanvasMoved('main', 'main.diff', 'u1', 3.0, 4.5, new Modifiers()), 'view.moved.main.main.diff'],
    'canvas entered' => [fn (): PrimitiveMail => new CanvasEntered('main', 'main.diff', 'u1'), 'view.entered.main.main.diff'],
    'canvas left' => [fn (): PrimitiveMail => new CanvasLeft('main', 'main.diff', 'u1'), 'view.left.main.main.diff'],
]);

it('reads its fields back', function (): void {
    $moved = new CanvasMoved('main', 'main.diff', 'u1', 3.0, 4.5, new Modifiers(shift: true));
    $scroll = new ScrollChanged('main', 'main.diff.bar', 'u2', 40);
    $left = new CanvasLeft('main', 'main.diff', 'u3');

    expect([$moved->window, $moved->path, $moved->uuid, $moved->x, $moved->y, $moved->modifiers->shift])->toBe(['main', 'main.diff', 'u1', 3.0, 4.5, true])
        ->and([$scroll->uuid, $scroll->value])->toBe(['u2', 40])
        ->and($left->uuid)->toBe('u3');
});

it('gives a wheel a phase, none unless the device reports one', function (): void {
    $notch = new CanvasWheeled('main', 'main.diff', 'u1', 0.0, 1.0, false, new Modifiers());
    $swipe = new CanvasWheeled('main', 'main.diff', 'u1', 0.0, 3.5, true, new Modifiers(), ScrollPhase::MOMENTUM);

    expect($notch->phase)->toBe(ScrollPhase::NONE)
        ->and($swipe->phase)->toBe(ScrollPhase::MOMENTUM)
        ->and(array_map(fn (ScrollPhase $phase): string => $phase->value, ScrollPhase::cases()))->toBe(['none', 'began', 'changed', 'ended', 'momentum', 'momentum-ended']);
});
