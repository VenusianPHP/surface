<?php

declare(strict_types=1);

use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\Windows\Mail\View\FocusGained;
use Surface\Contracts\Windows\Mail\View\FocusLost;
use Surface\Contracts\Windows\Mail\View\PrimitiveMail;
use Surface\Contracts\Windows\Mail\WindowKeyPressed;
use Surface\Contracts\Windows\Mail\WindowMail;

it('names the focus mail by window and path', function (): void {
    $gained = new FocusGained('main', 'm.first', 'u1');
    $lost = new FocusLost('main', 'm.first', 'u1');

    expect($gained)->toBeInstanceOf(PrimitiveMail::class)
        ->and($gained->name())->toBe('view.focus-gained.main.m.first')
        ->and([$gained->window, $gained->path, $gained->uuid])->toBe(['main', 'm.first', 'u1'])
        ->and($lost)->toBeInstanceOf(PrimitiveMail::class)
        ->and($lost->name())->toBe('view.focus-lost.main.m.first');
});

it('names a window key by window and carries the key, its text, modifiers and repeat', function (): void {
    $mail = new WindowKeyPressed('main', Key::F5, '', new Modifiers(ctrl: true), true);

    expect($mail)->toBeInstanceOf(WindowMail::class)
        ->and($mail->name())->toBe('window.key.main')
        ->and($mail->key)->toBe(Key::F5)
        ->and($mail->character)->toBe('')
        ->and($mail->modifiers->ctrl)->toBeTrue()
        ->and($mail->repeat)->toBeTrue();
});
