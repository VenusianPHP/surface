<?php

declare(strict_types=1);

use Surface\Contracts\Windows\WindowException;
use Venusian\Surface\Tests\Fixtures\FakeHost;

/*
 * Scroll bar, toolkit-neutral: the value runs 0..max(0, maximum - page) and is clamped on every write.
 */

it('starts empty at zero, a step of one, vertical unless asked', function (): void {
    $column = (new FakeHost('main'))->column('m');
    $bar = $column->scrollBar('s');

    expect($bar->vertical())->toBeTrue()
        ->and($bar->maximum())->toBe(0)
        ->and($bar->page())->toBe(0)
        ->and($bar->step())->toBe(1)
        ->and($bar->value())->toBe(0)
        ->and($column->scrollBar('h', false)->vertical())->toBeFalse();
});

it('clamps the value to the range less a page', function (): void {
    $bar = (new FakeHost('main'))->column('m')->scrollBar('s');

    $bar->setRange(1000, 100)->setValue(950);

    expect($bar->value())->toBe(900)
        ->and($bar->log)->toContain('range:1000,100,0')
        ->and($bar->log)->toContain('value:900');
});

it('pulls the value in when the range shrinks under it', function (): void {
    $bar = (new FakeHost('main'))->column('m')->scrollBar('s');
    $bar->setRange(1000, 100)->setValue(900);

    $bar->setRange(500, 100);

    expect($bar->value())->toBe(400)
        ->and($bar->log)->toContain('range:500,100,400');
});

it('refuses a negative range and a step under one', function (): void {
    $bar = (new FakeHost('main'))->column('m')->scrollBar('s');

    expect(fn () => $bar->setRange(-1, 0))->toThrow(WindowException::class, 'Scroll bar maximum and page must not be negative.')
        ->and(fn () => $bar->setRange(10, -1))->toThrow(WindowException::class, 'Scroll bar maximum and page must not be negative.')
        ->and(fn () => $bar->setStep(0))->toThrow(WindowException::class, 'Scroll bar step must be at least 1.');

    $bar->setStep(40);
    expect($bar->step())->toBe(40)->and($bar->log)->toContain('step:40');
});

it('stores the native\'s move clamped', function (): void {
    $bar = (new FakeHost('main'))->column('m')->scrollBar('s');
    $bar->setRange(1000, 100);

    $bar->nativeMoved(2000);
    expect($bar->value())->toBe(900);

    $bar->nativeMoved(-5);
    expect($bar->value())->toBe(0);
});

it('holds zero while the page covers the range', function (): void {
    $bar = (new FakeHost('main'))->column('m')->scrollBar('s');
    $bar->setRange(100, 300);

    $bar->setValue(50);
    expect($bar->value())->toBe(0);

    $bar->nativeMoved(70);
    expect($bar->value())->toBe(0);
});
