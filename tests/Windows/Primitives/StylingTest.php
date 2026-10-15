<?php

declare(strict_types=1);

use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\FontWeight;
use Surface\Contracts\Windows\Styling\ScrollPosition;
use Surface\Contracts\Windows\Styling\TextAlignment;
use Surface\Contracts\Windows\Styling\TextRangeStyle;
use Surface\NutsAndBolts\Color;

it('maps each font weight to its css weight', function (): void {
    expect(array_map(fn (FontWeight $w): int => $w->toCssWeight(), FontWeight::cases()))->toBe([300, 400, 500, 600, 700, 900]);
});

it('defaults a font to the regular weight of the system family and refuses a size that is not positive', function (): void {
    $font = new FontSpec(13.0);

    expect($font->weight)->toBe(FontWeight::REGULAR)
        ->and($font->family)->toBeNull()
        ->and((new FontSpec(18.0, FontWeight::BOLD, 'Menlo'))->family)->toBe('Menlo')
        ->and(fn () => new FontSpec(0.0))->toThrow(InvalidArgumentException::class, 'positive')
        ->and(fn () => new FontSpec(-2.0))->toThrow(InvalidArgumentException::class, 'positive')
        ->and(fn () => new FontSpec(NAN))->toThrow(InvalidArgumentException::class, 'positive')
        ->and(fn () => new FontSpec(INF))->toThrow(InvalidArgumentException::class, 'positive');
});

it('aligns text left, center or right', function (): void {
    expect(array_map(fn (TextAlignment $a): string => $a->value, TextAlignment::cases()))->toBe(['left', 'center', 'right']);
});

it('asks for the fixed-pitch face with monospace and keeps the family when one is named', function (): void {
    expect((new FontSpec(12.0))->monospace)->toBeFalse()
        ->and((new FontSpec(12.0, monospace: true))->monospace)->toBeTrue()
        ->and((new FontSpec(12.0, family: 'Menlo', monospace: true))->family)->toBe('Menlo');
});

it('refuses a range style with negative values or no colour', function (): void {
    $style = new TextRangeStyle(1, 2, 3, foreground: Color::hex('#ff0000'));

    expect([$style->line, $style->start, $style->length])->toBe([1, 2, 3])
        ->and(fn () => new TextRangeStyle(-1, 0, 1, foreground: Color::hex('#ff0000')))->toThrow(InvalidArgumentException::class, 'not negative')
        ->and(fn () => new TextRangeStyle(0, 0, -1, background: Color::hex('#ff0000')))->toThrow(InvalidArgumentException::class, 'not negative')
        ->and(fn () => new TextRangeStyle(0, 0, 1))->toThrow(InvalidArgumentException::class, 'a foreground or a background');
});

it('measures a scroll position as content fractions and turns a fraction back into an offset', function (): void {
    $at = ScrollPosition::of(0.0, 100.0, 100.0, 250.0, 100.0, 1000.0);

    expect([$at->x, $at->y, $at->width, $at->height])->toBe([0.0, 0.25, 1.0, 0.1])
        ->and(ScrollPosition::of(0.0, 50.0, 0.0, 0.0, 50.0, 0.0))->toEqual(new ScrollPosition(0.0, 0.0, 1.0, 1.0))
        ->and(ScrollPosition::of(0.0, 10.0, 100.0, 990.0, 100.0, 1000.0)->y)->toBe(0.9)
        ->and(ScrollPosition::offset(0.25, 100.0, 1000.0))->toBe(250.0)
        ->and(ScrollPosition::offset(1.0, 100.0, 1000.0))->toBe(900.0)
        ->and(ScrollPosition::offset(0.5, 100.0, 50.0))->toBe(0.0)
        ->and(fn () => new ScrollPosition(0.0, 1.5, 1.0, 1.0))->toThrow(InvalidArgumentException::class, '0..1')
        ->and(fn () => new ScrollPosition(INF, 0.0, 1.0, 1.0))->toThrow(InvalidArgumentException::class, '0..1');
});
