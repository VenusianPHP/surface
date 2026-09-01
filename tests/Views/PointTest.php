<?php

use Surface\Contracts\NativeWindows\Views\ViewException;
use Surface\NativeWindows\Enums\ImageFit;
use Surface\NativeWindows\Enums\Orientation;
use Surface\NativeWindows\Views\Point;

it('exposes integer coordinates', function () {
    $point = new Point(12, -4);

    expect($point->x)->toBe(12)
        ->and($point->y)->toBe(-4);
});

it('names orientations so HORIZONTAL is panes side by side', function () {
    expect(Orientation::HORIZONTAL->value)->toBe('horizontal')
        ->and(Orientation::VERTICAL->value)->toBe('vertical');
});

it('names image fit modes as CSS-shaped strings', function () {
    expect(ImageFit::CONTAIN->value)->toBe('contain')
        ->and(ImageFit::COVER->value)->toBe('cover')
        ->and(ImageFit::STRETCH->value)->toBe('stretch')
        ->and(ImageFit::NONE->value)->toBe('none');
});

it('explains native-owned, bad source and foreign-anchor failures', function () {
    expect(ViewException::nativeOwned('main.first', 'move')->getMessage())
        ->toContain('main.first')
        ->toContain('move')
        ->and(ViewException::badSource('/nope.png')->getMessage())->toContain('/nope.png')
        ->and(ViewException::foreignAnchor('tip')->getMessage())->toContain('tip');
});
