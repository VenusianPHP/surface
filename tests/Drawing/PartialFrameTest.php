<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\BitOrder;
use Surface\Contracts\Framebuffers\DamageTrackingFramebuffer;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Framebuffers\ScanDirection;
use Surface\Drawing\Velvet\VelvetGE;
use Surface\Fonts\ClassicFont;
use Surface\NutsAndBolts\Color;

/*
 * A frame is compared with the one before it: only where they differ is drawn
 * and reported as damage. Drawn that way, every framebuffer must end up byte
 * for byte as it would redrawn whole.
 */

function partialDrivers(): array
{
    return class_exists(FbBuffer::class) ? ['native', 'extended'] : ['native'];
}

/** @return array<string, Closure(string): Framebuffer> */
function partialKinds(): array
{
    $mono = new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1, ScanDirection::TOP_TO_BOTTOM, BitOrder::MSB_FIRST);

    return [
        'full' => fn (string $driver) => framebuffers()->driver($driver)->full(FormatSpec::rgba8(), 64, 32),
        'dirty' => fn (string $driver) => framebuffers()->driver($driver)->dirty(FormatSpec::rgba8(), 64, 32),
        'epaper' => fn (string $driver) => framebuffers()->driver($driver)->epaper($mono, 64, 32),
        'ring' => fn (string $driver) => framebuffers()->driver($driver)->ring(FormatSpec::rgba8(), 64, 32, 2),
    ];
}

function partialEngine(Framebuffer $framebuffer): VelvetGE
{
    return new VelvetGE($framebuffer, rasterize()->driver());
}

function partialTile(): Framebuffer
{
    $tile = framebuffers()->driver('native')->full(FormatSpec::rgba8(), 8, 8);
    $tile->fill(0xFF0000FF);

    return $tile;
}

/** Frame $i of a clock: a border that stays, a dot that moves, digits that change, a line that grows, a fixed image, and a triangle on frame 3 only. */
function clockFrame(RenderingEngine $g, int $i, Framebuffer $tile): void
{
    $g->clear(Color::rgb(0, 0, 0));
    $g->strokeRect(1, 1, 62, 30, Color::rgb(255, 255, 255));
    $g->fillEllipse(10 + 3 * $i, 16, 4, 3, Color::rgba(255, 128, 0, 0.8));
    $g->text(sprintf('%02d', 10 + $i), 30, 4, Color::rgb(255, 255, 255), new ClassicFont);
    $g->line(2, 28, 20 + 5 * $i, 20, Color::rgb(0, 255, 0), 1.5);
    $g->image($tile, 44, 18);
    if ($i === 3) {
        $g->push()->translate(0.5, 0.5)->fillTriangle(40, 2, 46, 10, 36, 9, Color::rgb(0, 0, 255))->pop();
    }
}

it('draws a sequence of frames byte for byte as whole redraws would', function (string $kind, string $driver) {
    $make = partialKinds()[$kind];
    $partial = partialEngine($make($driver));
    $whole = partialEngine($make($driver));
    $tile = partialTile();

    foreach (range(0, 4) as $i) {
        $partial->frame(fn (RenderingEngine $g) => clockFrame($g, $i, $tile));
        $whole->invalidate()->frame(fn (RenderingEngine $g) => clockFrame($g, $i, $tile));

        expect($partial->framebuffer()->dump())->toBe($whole->framebuffer()->dump(), "frame {$i}");
    }
})->with(array_keys(partialKinds()))->with(partialDrivers());

it('reports only what changed after the first frame', function () {
    $engine = partialEngine(framebuffers()->driver('native')->dirty(FormatSpec::rgba8(), 64, 32));
    $tile = partialTile();

    $engine->frame(fn (RenderingEngine $g) => clockFrame($g, 0, $tile));
    $first = $engine->damage();
    $engine->frame(fn (RenderingEngine $g) => clockFrame($g, 1, $tile));

    $area = array_sum(array_map(fn (Region $r): int => $r->width * $r->height, $engine->damage()));
    expect($first)->toEqual([new Region(0, 0, 64, 32)])
        ->and($area)->toBeGreaterThan(0)
        ->and($area)->toBeLessThan(64 * 32);
});

it('draws and reports nothing for a frame identical to the last', function () {
    $framebuffer = framebuffers()->driver('native')->dirty(FormatSpec::rgba8(), 64, 32);
    $engine = partialEngine($framebuffer);
    $still = function (RenderingEngine $g): void {
        $g->clear(Color::rgb(0, 0, 0));
        $g->strokeRect(1, 1, 62, 30, Color::rgb(255, 255, 255));
        $g->fillEllipse(16, 16, 4, 3, Color::rgba(255, 128, 0, 0.8));
        $g->text('12', 30, 4, Color::rgb(255, 255, 255), new ClassicFont);
    };
    $engine->frame($still);
    $framebuffer->beginEpoch();

    $engine->frame($still);

    expect($engine->damage())->toBe([])
        ->and($framebuffer->damage())->toBe([]);
});

it('writes nothing outside the reported damage when a shape moves', function () {
    $framebuffer = framebuffers()->driver('native')->dirty(FormatSpec::rgba8(), 64, 32);
    $engine = partialEngine($framebuffer);
    $draw = fn (float $x) => function (RenderingEngine $g) use ($x): void {
        $g->clear(Color::rgb(0, 0, 0));
        $g->fillRect($x, 10, 4, 4, Color::rgb(255, 255, 255));
    };
    $engine->frame($draw(10));
    $framebuffer->beginEpoch();

    $engine->frame($draw(20));

    $damage = $engine->damage();
    $covers = fn (int $x0, int $x1): bool => array_filter($damage, fn (Region $r): bool => $r->x <= $x0 && $r->right() >= $x1 && $r->y <= 10 && $r->bottom() >= 14) !== [];
    expect($covers(10, 14))->toBeTrue('the old box')
        ->and($covers(20, 24))->toBeTrue('the new box')
        ->and(array_sum(array_map(fn (Region $r): int => $r->width * $r->height, $damage)))->toBeLessThan(64 * 32);
    foreach ($framebuffer->damage() as $written) {
        $inside = array_filter($damage, fn (Region $r): bool => $written->intersect($r) == $written);
        expect($inside)->not->toBe([]);
    }
});

it('draws a frame without a leading clear whole, and reports the boxes of its commands', function () {
    $engine = new RecordingEngine(64, 32);
    $engine->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0)));
    $commands = $engine->commandsOf(fn (RenderingEngine $g) => $g->fillRect(10, 10, 4, 4, Color::rgb(255, 255, 255)));

    expect($commands)->toHaveCount(1)
        ->and($commands[0][4])->toEqual(new Region(0, 0, 64, 32))
        ->and($engine->damage())->toEqual([new Region(9, 9, 6, 6)]);
});

it('reports the whole surface for every frame of a paged framebuffer', function () {
    $paged = framebuffers()->driver('native')->paged(FormatSpec::rgba8(), 64, 32, 8);
    $engine = partialEngine($paged);
    $tile = partialTile();

    $engine->frame(fn (RenderingEngine $g) => clockFrame($g, 0, $tile));
    $engine->frame(fn (RenderingEngine $g) => clockFrame($g, 0, $tile));

    expect($engine->damage())->toEqual([new Region(0, 0, 64, 32)]);
});

it('counts an image as changed, so new pixels in the same source are drawn', function () {
    $partial = partialEngine(framebuffers()->driver('native')->dirty(FormatSpec::rgba8(), 64, 32));
    $whole = partialEngine(framebuffers()->driver('native')->dirty(FormatSpec::rgba8(), 64, 32));
    $tile = partialTile();
    $partial->frame(fn (RenderingEngine $g) => clockFrame($g, 1, $tile));

    $tile->fill(0x0000FFFF);
    $partial->frame(fn (RenderingEngine $g) => clockFrame($g, 1, $tile));
    $whole->frame(fn (RenderingEngine $g) => clockFrame($g, 1, $tile));

    expect($partial->framebuffer()->dump())->toBe($whole->framebuffer()->dump())
        ->and($partial->damage())->not->toBe([]);
});

it('redraws whole after invalidate(), covering writes made outside the engine', function () {
    $framebuffer = framebuffers()->driver('native')->full(FormatSpec::rgba8(), 64, 32);
    $engine = partialEngine($framebuffer);
    $tile = partialTile();
    $engine->frame(fn (RenderingEngine $g) => clockFrame($g, 1, $tile));
    $clean = $framebuffer->dump();

    $framebuffer->setPixel(60, 2, 0xFFFFFFFF);
    $engine->invalidate()->frame(fn (RenderingEngine $g) => clockFrame($g, 1, $tile));

    expect($framebuffer->dump())->toBe($clean)
        ->and($engine->damage())->toEqual([new Region(0, 0, 64, 32)]);
});
