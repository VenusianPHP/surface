<?php

declare(strict_types=1);

use Surface\Contracts\Windows\WindowException;
use Venusian\Surface\Tests\Fixtures\FakeHost;

/*
 * Focus, toolkit-neutral: the fake native takes focus unless $takes_focus is false, and
 * reports $native_focus.
 */

it('focuses a shown primitive through the native and returns it', function (): void {
    $label = (new FakeHost('main'))->column('m')->label('l', 'x');

    expect($label->focus())->toBe($label)
        ->and($label->log)->toContain('focus')
        ->and($label->hasFocus())->toBeTrue();
});

it('refuses focus to a hidden primitive, without asking the native', function (): void {
    $label = (new FakeHost('main'))->column('m')->label('l', 'x');
    $label->hide();

    expect(fn () => $label->focus())->toThrow(WindowException::class, "'m.l' cannot take focus: it is not shown.")
        ->and($label->log)->not->toContain('focus');
});

it('refuses focus under a hidden container, without asking the native', function (): void {
    $host = new FakeHost('main');
    $label = $host->column('m')->row('r')->label('l', 'x');
    $host->view('m')->hide();

    expect(fn () => $label->focus())->toThrow(WindowException::class, "'m.r.l' cannot take focus: it is not shown.")
        ->and($label->log)->not->toContain('focus');
});

it('refuses focus to a disabled primitive or one under a disabled container, without asking the native', function (string $disabled): void {
    $host = new FakeHost('main');
    $label = $host->column('m')->row('r')->label('l', 'x');
    $host->view($disabled)->setEnabled(false);

    expect(fn () => $label->focus())->toThrow(WindowException::class, "'m.r.l' cannot take focus.")
        ->and($label->log)->not->toContain('focus');
})->with(['itself' => 'm.r.l', 'container' => 'm.r']);

it('refuses focus the native declines', function (): void {
    $label = (new FakeHost('main'))->column('m')->label('l', 'x');
    $label->takes_focus = false;

    expect(fn () => $label->focus())->toThrow(WindowException::class, "'m.l' cannot take focus.");
});

it('refuses focus and the focus read on a removed primitive', function (): void {
    $label = (new FakeHost('main'))->column('m')->label('l', 'x');
    $label->remove();

    expect(fn () => $label->focus())->toThrow(WindowException::class)
        ->and(fn () => $label->hasFocus())->toThrow(WindowException::class);
});

it('reads focus from the native', function (): void {
    $label = (new FakeHost('main'))->column('m')->label('l', 'x');

    expect($label->hasFocus())->toBeFalse();
    $label->native_focus = true;
    expect($label->hasFocus())->toBeTrue();
});

it('starts a canvas unfocusable and not taking Tab', function (): void {
    $canvas = (new FakeHost('main'))->column('m')->canvas('c');

    expect($canvas->isFocusable())->toBeFalse()
        ->and($canvas->takesTab())->toBeFalse();
});

it('sets a canvas focusable once per change, and takes Tab only while focusable', function (): void {
    $canvas = (new FakeHost('main'))->column('m')->canvas('c');

    $canvas->setFocusable(true, true)->setFocusable(true, true);
    expect($canvas->isFocusable())->toBeTrue()
        ->and($canvas->takesTab())->toBeTrue()
        ->and(array_values(array_filter($canvas->log, fn (string $entry): bool => str_starts_with($entry, 'focusable:'))))->toBe(['focusable:1,1']);

    $canvas->setFocusable(false, true);
    expect($canvas->isFocusable())->toBeFalse()
        ->and($canvas->takesTab())->toBeFalse()
        ->and($canvas->log)->toContain('focusable:0,0');
});
