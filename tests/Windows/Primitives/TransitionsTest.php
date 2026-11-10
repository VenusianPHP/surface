<?php

declare(strict_types=1);

use Surface\Contracts\Windows\Primitives\TKTransition;
use Surface\Contracts\Windows\WindowException;
use Venusian\Surface\Tests\Fixtures\FakeHost;

it('passes opacity to the engine and refuses one outside 0.0..1.0', function (): void {
    $label = (new FakeHost('main'))->column('m')->label('a', 'A');
    $label->log = [];

    $label->setOpacity(0.4);

    expect($label->opacity())->toBe(0.4)
        ->and($label->log)->toBe(['opacity:0.4'])
        ->and(fn () => $label->setOpacity(1.5))->toThrow(WindowException::class)
        ->and(fn () => $label->setOpacity(-0.1))->toThrow(WindowException::class);
});

it('shows and hides at once without transitions', function (): void {
    $label = (new FakeHost('main'))->column('m')->label('a', 'A');
    $label->log = [];

    $label->hide()->show();

    expect($label->log)->toBe(['visible:0', 'visible:1']);
});

it('shows the native, then plays the appear transition', function (): void {
    $label = (new FakeHost('main'))->column('m')->label('a', 'A');
    $label->appear(TKTransition::SLIDE_UP, 0.3)->hide();
    $label->log = [];

    $label->show();

    expect($label->isVisible())->toBeTrue()
        ->and($label->log)->toBe(['visible:1', 'transition:slide-up:in:0.3']);
});

it('plays the disappear transition and hides the native only once it lands', function (): void {
    $label = (new FakeHost('main'))->column('m')->label('a', 'A');
    $label->disappear(TKTransition::FADE);
    $label->log = [];

    $label->hide();

    expect($label->isVisible())->toBeFalse()
        ->and($label->log)->toBe(['transition:fade:out:0.25']);

    ($label->landing)();

    expect($label->log)->toBe(['transition:fade:out:0.25', 'visible:0']);
});

it('keeps the native shown when it is shown again before the disappear transition lands', function (): void {
    $label = (new FakeHost('main'))->column('m')->label('a', 'A');
    $label->disappear(TKTransition::FADE);
    $label->hide();
    $land = $label->landing;
    $label->show();
    $label->log = [];

    $land();

    expect($label->isVisible())->toBeTrue()
        ->and($label->log)->toBe([]);
});

it('refuses a negative duration', function (): void {
    $label = (new FakeHost('main'))->column('m')->label('a', 'A');

    expect(fn () => $label->appear(TKTransition::FADE, -1.0))->toThrow(WindowException::class)
        ->and(fn () => $label->disappear(TKTransition::FADE, -1.0))->toThrow(WindowException::class);
});

it('gives each slide its starting offset', function (): void {
    expect(TKTransition::FADE->offset())->toBe([0, 0])
        ->and(TKTransition::SLIDE_UP->offset())->toBe([0, 24])
        ->and(TKTransition::SLIDE_DOWN->offset())->toBe([0, -24])
        ->and(TKTransition::SLIDE_LEFT->offset())->toBe([24, 0])
        ->and(TKTransition::SLIDE_RIGHT->offset())->toBe([-24, 0]);
});
