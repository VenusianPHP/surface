<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Drawing\Effects\Blur;
use Surface\Contracts\Drawing\Effects\EdgeMode;
use Surface\Contracts\Drawing\Effects\FilterChain;
use Surface\Contracts\Drawing\Effects\Flood;
use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\RasterizeDriver;
use Surface\Drawing\Velvet\VelvetGE;
use Surface\NutsAndBolts\Color;

/*
 * layer() on VelvetGE, on every framebuffer and rasterize driver: a group drawn apart and laid down in one piece.
 */

function layered(FramebufferDriver $fb, RasterizeDriver $raster, int $width = 40, int $height = 24): array
{
    $buffer = $fb->full(FormatSpec::rgba8(), $width, $height);

    return [$buffer, new VelvetGE($buffer, $raster, Edges::HARD)];
}

function layerPx(Framebuffer $buffer, int $x, int $y): string
{
    return sprintf('%08x', $buffer->getPixel($x, $y));
}

const LAYER_WHITE = '#ffffff';

it('lays down what it holds, clipped to its box', function (FramebufferDriver $fb, RasterizeDriver $raster): void {
    [$buffer, $g] = layered($fb, $raster);
    $g->frame(function (RenderingEngine $g): void {
        $g->clear(Color::hex(LAYER_WHITE));
        $g->layer(4, 4, 10, 10, fn (RenderingEngine $l) => $l->fillRect(0, 0, 40, 24, Color::hex('#ff0000')));
    });

    expect(layerPx($buffer, 4, 4))->toBe('ff0000ff')
        ->and(layerPx($buffer, 13, 13))->toBe('ff0000ff')
        ->and(layerPx($buffer, 3, 4))->toBe('ffffffff')
        ->and(layerPx($buffer, 14, 14))->toBe('ffffffff');
})->with('framebuffer drivers')->with('rasterize drivers');

it('fades a group as one: what is drawn first does not show through what is drawn over it', function (FramebufferDriver $fb, RasterizeDriver $raster): void {
    [$buffer, $g] = layered($fb, $raster);
    $g->frame(function (RenderingEngine $g): void {
        $g->clear(Color::hex(LAYER_WHITE));
        $g->layer(0, 0, 20, 20, function (RenderingEngine $l): void {
            $l->fillRect(0, 0, 10, 10, Color::hex('#ff0000'));
            $l->fillRect(5, 5, 10, 10, Color::hex('#0000ff'));
        }, 0.5);
    });

    // alpha 128 over white: (c × 128 + 255 × 127 + 127) / 255; the overlap is the blue alone, with no red in it
    expect(layerPx($buffer, 2, 2))->toBe('ff7f7fff')
        ->and(layerPx($buffer, 7, 7))->toBe('7f7fffff')
        ->and(layerPx($buffer, 12, 12))->toBe('7f7fffff');
})->with('framebuffer drivers')->with('rasterize drivers');

it('keeps a translucent shape the colour it was drawn in', function (FramebufferDriver $fb, RasterizeDriver $raster): void {
    [$buffer, $g] = layered($fb, $raster);
    $g->frame(function (RenderingEngine $g): void {
        $g->clear(Color::hex('#000000'));
        $g->layer(0, 0, 10, 10, fn (RenderingEngine $l) => $l->fillRect(0, 0, 10, 10, Color::rgba(255, 0, 0, 0.5)));
    });
    $direct = $fb->full(FormatSpec::rgba8(), 40, 24);
    $d = new VelvetGE($direct, $raster, Edges::HARD);
    $d->frame(function (RenderingEngine $g): void {
        $g->clear(Color::hex('#000000'));
        $g->fillRect(0, 0, 10, 10, Color::rgba(255, 0, 0, 0.5));
    });

    // a layer of one translucent shape lands as the shape would have: premultiplied while apart, straight again after (±1 for the round trip)
    $a = $buffer->getPixel(5, 5);
    $b = $direct->getPixel(5, 5);
    foreach ([24, 16, 8] as $shift) {
        expect(abs((($a >> $shift) & 0xFF) - (($b >> $shift) & 0xFF)))->toBeLessThanOrEqual(1);
    }
})->with('framebuffer drivers')->with('rasterize drivers');

it('cuts a layer to a rounded box', function (FramebufferDriver $fb, RasterizeDriver $raster): void {
    [$buffer, $g] = layered($fb, $raster);
    $g->frame(function (RenderingEngine $g): void {
        $g->clear(Color::hex(LAYER_WHITE));
        $g->layer(0, 0, 16, 16, fn (RenderingEngine $l) => $l->fillRect(0, 0, 16, 16, Color::hex('#00aa00')), radii: [8, 8, 8, 8]);
    });

    expect(layerPx($buffer, 0, 0))->toBe('ffffffff')
        ->and(layerPx($buffer, 15, 15))->toBe('ffffffff')
        ->and(layerPx($buffer, 8, 8))->toBe('00aa00ff');
})->with('framebuffer drivers')->with('rasterize drivers');

it('runs a filter over what the layer holds', function (FramebufferDriver $fb, RasterizeDriver $raster): void {
    [$buffer, $g] = layered($fb, $raster);
    $g->frame(function (RenderingEngine $g): void {
        $g->clear(Color::hex(LAYER_WHITE));
        // nothing drawn in it: the flood is all there is
        $g->layer(2, 2, 6, 6, fn (RenderingEngine $l) => $l->fillRect(2, 2, 1, 1, Color::hex('#000000')), 1.0, null, FilterChain::of(new Flood(Color::hex('#336699'))));
    });

    expect(layerPx($buffer, 2, 2))->toBe('336699ff')
        ->and(layerPx($buffer, 7, 7))->toBe('336699ff')
        ->and(layerPx($buffer, 8, 8))->toBe('ffffffff');
})->with('framebuffer drivers')->with('rasterize drivers');

it('blurs what lies under the box, inside the box only', function (FramebufferDriver $fb, RasterizeDriver $raster): void {
    [$buffer, $g] = layered($fb, $raster);
    $g->frame(function (RenderingEngine $g): void {
        $g->fillRect(0, 0, 20, 24, Color::hex('#ff0000'));
        $g->fillRect(20, 0, 20, 24, Color::hex('#0000ff'));
        $g->layer(10, 4, 20, 16, fn () => null, 1.0, null, null, FilterChain::of(Blur::gaussian(3.0, 3.0, EdgeMode::DUPLICATE)));
    });

    // at the red/blue edge inside the box the colours mix; outside the box the edge stays hard; deep in red stays red
    $left = $buffer->getPixel(19, 12);
    $right = $buffer->getPixel(20, 12);
    expect(($left >> 24) & 0xFF)->toBeLessThan(255)->toBeGreaterThan(100)
        ->and(($left >> 8) & 0xFF)->toBeGreaterThan(0)
        ->and(layerPx($buffer, 19, 2))->toBe('ff0000ff')
        ->and(layerPx($buffer, 20, 2))->toBe('0000ffff')
        ->and(layerPx($buffer, 11, 12))->toBe('ff0000ff')
        ->and(abs(($left >> 24) - (($right >> 24) & 0xFF)))->toBeGreaterThan(0);
})->with('framebuffer drivers')->with('rasterize drivers');

it('draws a frame again after a change as a fresh frame draws it, backdrop layers included', function (FramebufferDriver $fb, RasterizeDriver $raster): void {
    $scene = function (int $dot): Closure {
        return function (RenderingEngine $g) use ($dot): void {
            $g->clear(Color::hex('#202020'));
            $g->fillRect(0, 0, 8, 24, Color::hex('#ff8800'));
            $g->fillRect($dot, 2, 3, 3, Color::hex('#ffffff'));
            $g->layer(20, 4, 14, 14, fn (RenderingEngine $l) => $l->fillRect(0, 0, 40, 24, Color::rgba(0, 120, 255, 0.4)), 1.0, [4, 4, 4, 4], null, FilterChain::of(Blur::gaussian(3.0, 3.0, EdgeMode::DUPLICATE)));
        };
    };
    [$a, $ga] = layered($fb, $raster);
    $ga->frame($scene(10));
    $ga->frame($scene(17));   // the dot moves beside the layer, inside its read area but outside its box
    [$b, $gb] = layered($fb, $raster);
    $gb->frame($scene(17));

    expect(bin2hex($a->dump()))->toBe(bin2hex($b->dump()));
    $box = new Region(20, 4, 14, 14);
    expect(array_filter($ga->damage(), fn (Region $r): bool => $r->x <= $box->x && $r->y <= $box->y && $r->right() >= $box->right() && $r->bottom() >= $box->bottom()))->not->toBe([]);
})->with('framebuffer drivers')->with('rasterize drivers');

it('lays a layer inside a layer', function (FramebufferDriver $fb, RasterizeDriver $raster): void {
    [$buffer, $g] = layered($fb, $raster);
    $g->frame(function (RenderingEngine $g): void {
        $g->clear(Color::hex(LAYER_WHITE));
        $g->layer(0, 0, 30, 20, function (RenderingEngine $l): void {
            $l->layer(10, 10, 10, 10, fn (RenderingEngine $m) => $m->fillRect(0, 0, 40, 24, Color::hex('#000000')), 0.5);
        }, 0.5);
    });

    // black at 50% inside, that at 50% again over white: 255 → 127 (inner) → ~191
    $v = ($buffer->getPixel(15, 15) >> 24) & 0xFF;
    expect($v)->toBeGreaterThanOrEqual(190)->toBeLessThanOrEqual(192)
        ->and(layerPx($buffer, 5, 5))->toBe('ffffffff');
})->with('framebuffer drivers')->with('rasterize drivers');

it('refuses a turned layer and a push left open', function (RasterizeDriver $raster): void {
    [$buffer, $g] = layered(new Surface\Framebuffers\Native\NativeFramebufferDriver(), $raster);
    $g->begin();
    $g->rotate(0.3);
    expect(fn () => $g->layer(0, 0, 10, 10, fn () => null, 1.0, null, FilterChain::of()))->toThrow(DrawingException::class, 'upright transform');
    $g->end();
    $g->begin();
    expect(fn () => $g->layer(0, 0, 10, 10, fn (RenderingEngine $l) => $l->push()))->toThrow(DrawingException::class, 'push()');
    $g->end();
})->with('rasterize drivers');
