<?php

declare(strict_types=1);

use Surface\NutsAndBolts\Color;

it('builds from components, ints and hex, and renders css', function (): void {
    expect(Color::rgb(255, 0, 128)->toCss())->toBe('rgba(255, 0, 128, 1)')
        ->and(Color::hex('#ff0080')->red)->toBe(1.0)
        ->and(Color::hex('#f08')->toHex())->toBe('#ff0088')
        ->and(Color::hex('#ff008080')->alpha)->toBe(128 / 255)
        ->and(Color::rgba(0, 0, 0, 0.5)->toHex())->toBe('#00000080')
        ->and(fn () => Color::hex('red'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new Color(2.0, 0.0, 0.0))->toThrow(InvalidArgumentException::class);
});

it('refuses a trailing newline and non-finite components', function (): void {
    expect(fn () => Color::hex("#ff0080\n"))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Color::hex("#fff\n"))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new Color(NAN, 0.0, 0.0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new Color(0.0, 0.0, 0.0, INF))->toThrow(InvalidArgumentException::class);
});
