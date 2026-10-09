<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Framebuffers\Region;
use Surface\Drawing\Gpu\DamageHistory;

/*
 * What each swapchain image is owed: one present's target damage in surface
 * pixels, and the union an image shown $age presents ago repaints.
 */

it('repaints the whole surface on the first present', function (): void {
    $history = new DamageHistory;

    expect($history->record([new Region(1, 1, 2, 2)], 640, 480, new Region(0, 0, 640, 480), 640, 480))->toEqual([new Region(0, 0, 640, 480)])
        ->and($history->since(1))->toEqual([new Region(0, 0, 640, 480)]);
});

it('offsets damage by where an unscaled target lands', function (): void {
    $history = new DamageHistory;
    $placed = new Region(10, 20, 320, 240);
    $history->record([], 320, 240, $placed, 340, 280);

    expect($history->record([new Region(5, 5, 10, 10)], 320, 240, $placed, 340, 280))->toEqual([new Region(15, 25, 10, 10)]);
});

it('grows damage of a scaled target by a surface pixel each side, inside where it lands', function (): void {
    $history = new DamageHistory;
    $placed = new Region(0, 40, 640, 400);                            // 320x200 letterboxed into 640x480
    $history->record([], 320, 200, $placed, 640, 480);

    expect($history->record([new Region(10, 10, 5, 5), new Region(0, 0, 1, 1)], 320, 200, $placed, 640, 480))
        ->toEqual([new Region(19, 59, 12, 12), new Region(0, 40, 3, 3)]);
});

it('answers an image the union of the presents since it was shown', function (): void {
    $history = new DamageHistory;
    $placed = new Region(0, 0, 64, 64);
    $history->record([], 64, 64, $placed, 64, 64);
    $history->record([new Region(0, 0, 4, 4)], 64, 64, $placed, 64, 64);
    $history->record([new Region(40, 40, 4, 4)], 64, 64, $placed, 64, 64);

    expect($history->since(1))->toEqual([new Region(40, 40, 4, 4)])
        ->and($history->since(2))->toEqual([new Region(0, 0, 4, 4), new Region(40, 40, 4, 4)])
        ->and($history->since(3))->toEqual([new Region(0, 0, 64, 64)]);
});

it('keeps only its depth of presents, and repaints everything for an age it cannot answer', function (): void {
    $history = new DamageHistory(2);
    $placed = new Region(0, 0, 64, 64);
    foreach ([[], [new Region(0, 0, 4, 4)], [new Region(8, 8, 4, 4)], [new Region(16, 16, 4, 4)]] as $damage) {
        $history->record($damage, 64, 64, $placed, 64, 64);
    }

    expect($history->since(2))->toEqual([new Region(8, 8, 4, 4), new Region(16, 16, 4, 4)])
        ->and($history->since(0))->toEqual([new Region(0, 0, 64, 64)])
        ->and($history->since(3))->toEqual([new Region(0, 0, 64, 64)]);
});

it('starts over when the surface resizes, the target moves or the target resizes', function (): void {
    $history = new DamageHistory;
    $history->record([], 64, 64, new Region(0, 0, 64, 64), 64, 64);

    $resized = $history->record([new Region(0, 0, 1, 1)], 64, 64, new Region(0, 0, 64, 64), 80, 64);
    $moved = $history->record([new Region(0, 0, 1, 1)], 64, 64, new Region(8, 0, 64, 64), 80, 64);
    $retargeted = $history->record([new Region(0, 0, 1, 1)], 32, 32, new Region(8, 0, 64, 64), 80, 64);

    expect([$resized, $moved, $retargeted])->toEqual(array_fill(0, 3, [new Region(0, 0, 80, 64)]))
        ->and($history->since(2))->toEqual([new Region(0, 0, 80, 64)]);
});

it("turns rects into GL's, origin bottom-left", function (): void {
    expect(DamageHistory::flipped([new Region(10, 0, 5, 4), new Region(0, 60, 64, 4)], 64))
        ->toEqual([new Region(10, 60, 5, 4), new Region(0, 0, 64, 4)]);
});

it('keeps at least one present, and answers nothing before the first', function (): void {
    expect(fn () => new DamageHistory(0))->toThrow(DrawingException::class, 'A damage history keeps at least 1 present, got 0.')
        ->and(fn () => (new DamageHistory)->since(1))->toThrow(DrawingException::class, 'Nothing was recorded: record() each present before since().');
});
