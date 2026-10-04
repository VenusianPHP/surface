<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Contracts\Framebuffers\Spans;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\FillRule;
use Surface\Contracts\Rasterize\Rasterizer;
use Surface\Contracts\Rasterize\Scanner;

it('names the two edge modes and the two fill rules', function (): void {
    expect(array_map(fn (Edges $e): string => $e->value, Edges::cases()))->toBe(['hard', 'antialiased'])
        ->and(array_map(fn (FillRule $r): string => $r->value, FillRule::cases()))->toBe(['non-zero', 'even-odd']);
});

it('keeps a shape and everything derived from it inside what a scanner takes', function (): void {
    expect(Rasterizer::LIMIT)->toBe(2.0 ** 24)
        ->and(Scanner::LIMIT)->toBe(2.0 ** 30);
});

it('packs a span as seven little-endian bytes and reads it back', function (): void {
    $spans = Spans::pack(0x0102, 0x0304, 0x0506, 0x07).Spans::pack(65535, 0, 65535);

    expect(bin2hex($spans))->toBe('02010403060507'.'ffff0000ffffff')
        ->and(Spans::count($spans))->toBe(2)
        ->and(Spans::unpack($spans))->toBe([[0x0102, 0x0304, 0x0506, 7], [65535, 0, 65535, 255]])
        ->and(Spans::unpack(''))->toBe([]);
});

it('refuses a span it cannot pack and bytes that are not whole spans', function (array $span): void {
    expect(fn () => Spans::pack(...$span))->toThrow(FramebufferException::class, 'A span is y, x and length in 0..65535');
})->with([[[-1, 0, 1, 1]], [[0, 65536, 1, 1]], [[0, 0, 0, 1]], [[0, 0, 65536, 1]], [[0, 0, 1, 256]], [[0, 0, 1, -1]]]);

it('refuses span bytes that are not a whole number of spans', function (): void {
    expect(fn () => Spans::count('123456'))->toThrow(FramebufferException::class, 'whole number of 7-byte spans, got 6 bytes')
        ->and(fn () => Spans::unpack('12345678'))->toThrow(FramebufferException::class);
});
