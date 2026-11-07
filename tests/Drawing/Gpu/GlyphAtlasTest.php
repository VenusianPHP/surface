<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Fonts\GlyphMask;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\Edges;
use Surface\Drawing\Gpu\GlyphAtlas;
use Surface\Drawing\Gpu\Op;

/*
 * GlyphAtlas: masks on shelves in one texture kept across frames; it resets only at the start of a frame.
 */

function atlasMask(int $w, int $h, string $byte = "\x80"): GlyphMask
{
    return new GlyphMask(str_repeat($byte, $w * $h), $w, $h, 0, $h);
}

/** @return list<Op> */
function opsOf(array $operations): array
{
    return array_map(fn (array $o) => $o[0], $operations);
}

it('places masks on shelves with a pixel between them', function () {
    $atlas = new GlyphAtlas(Edges::ANTIALIASED, 16);
    $ops = [];
    $atlas->prepare([['a', atlasMask(6, 4)], ['b', atlasMask(5, 7)], ['c', atlasMask(6, 3)]], $ops);

    expect(opsOf($ops))->toBe([Op::ATLAS, Op::MASK, Op::MASK, Op::MASK])
        ->and($ops[0])->toBe([Op::ATLAS, 0, 16, 16])
        ->and($atlas->where('a'))->toEqual(new Region(0, 0, 6, 4))
        ->and($atlas->where('b'))->toEqual(new Region(7, 0, 5, 7))
        ->and($atlas->where('c'))->toEqual(new Region(0, 8, 6, 3))
        ->and($ops[3])->toEqual([Op::MASK, 0, new Region(0, 8, 6, 3), str_repeat("\x80", 18)]);
});

it('uploads a mask once across frames', function () {
    $atlas = new GlyphAtlas(Edges::ANTIALIASED, 16);
    $first = [];
    $atlas->prepare([['a', atlasMask(6, 4)], ['a', atlasMask(6, 4)]], $first);
    $second = [];
    $atlas->prepare([['a', atlasMask(6, 4)]], $second);

    expect(opsOf($first))->toBe([Op::ATLAS, Op::MASK])
        ->and($second)->toBe([]);
});

it('resets only at the start of a frame when a frame does not fit', function () {
    $atlas = new GlyphAtlas(Edges::ANTIALIASED, 16);
    $ops = [];
    $atlas->prepare([['a', atlasMask(16, 8)], ['b', atlasMask(16, 7)]], $ops);
    $next = [];
    $atlas->prepare([['b', atlasMask(16, 7)], ['c', atlasMask(4, 4)]], $next);

    expect(opsOf($next))->toBe([Op::ATLAS, Op::MASK, Op::MASK])
        ->and($next[0])->toBe([Op::ATLAS, 0, 16, 16])
        ->and($atlas->where('b'))->toEqual(new Region(0, 0, 16, 7))
        ->and($atlas->where('c'))->toEqual(new Region(0, 8, 4, 4));
});

it('doubles when one frame alone does not fit, and refuses past 4096', function () {
    $atlas = new GlyphAtlas(Edges::ANTIALIASED, 16);
    $ops = [];
    $atlas->prepare([['a', atlasMask(20, 2)]], $ops);

    expect($ops[0])->toBe([Op::ATLAS, 0, 32, 32])
        ->and($atlas->size())->toBe(32)
        ->and(fn () => (new GlyphAtlas(Edges::ANTIALIASED, 4096))->prepare([['huge', new GlyphMask('', 4097, 1, 0, 1)]], $ops))
        ->toThrow(DrawingException::class, '4096x4096');
});

it('makes the atlas again after forget()', function () {
    $atlas = new GlyphAtlas(Edges::ANTIALIASED, 16);
    $ops = [];
    $atlas->prepare([['a', atlasMask(2, 2)]], $ops);
    $atlas->forget();
    $again = [];
    $atlas->prepare([['a', atlasMask(2, 2)]], $again);

    expect(opsOf($again))->toBe([Op::ATLAS, Op::MASK]);
});

it('thresholds bytes for hard edges', function () {
    $atlas = new GlyphAtlas(Edges::HARD, 16);
    $ops = [];
    $atlas->prepare([['a', new GlyphMask("\x00\x7F\x80\xFF", 4, 1, 0, 1)]], $ops);

    expect($ops[1][3])->toBe("\x00\x00\xFF\xFF");
});
