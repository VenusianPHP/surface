<?php

declare(strict_types=1);

use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\FontWeight;
use Surface\Contracts\Windows\Styling\TextAlignment;

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
