<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Drawing\Effects\Glass;
use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\RasterizeDriver;
use Surface\Drawing\Velvet\VelvetGE;
use Surface\Framebuffers\Extended\ExtendedFramebufferDriver;
use Surface\Framebuffers\Native\NativeFramebufferDriver;
use Surface\NutsAndBolts\Color;
use Venusian\Surface\Tests\Support\Framebuffers\Formats;

/*
 * glass(): a pane is the backdrop under its rounded box blurred by a dual-filter pyramid and shaded in one pass over
 * the box. The native store answers the extended one's bytes; Velvet draws it the same on every driver, paints only
 * inside the box, and redraws the area a pane reads when anything under it changes.
 */

/** A striped, noisy backdrop, the same for a seed. */
function glassBackdrop(Framebuffer $buffer, int $seed): Framebuffer
{
    [$w, $h] = [$buffer->viewportWidth(), $buffer->viewportHeight()];
    mt_srand($seed);
    $rgba = '';
    for ($i = 0; $i < $w * $h; $i++) {
        $rgba .= pack('N', (mt_rand(0, 0xFFFFFF) << 8) | 0xFF);
    }

    return $buffer->writeRgba8($rgba, $w, $h);
}

function glassLook(): Glass
{
    return new Glass(blur: 9.0, tint: new Color(0.2, 0.4, 0.9, 0.3), saturation: 1.4, light: 0.05, rim: new Color(1, 1, 1, 0.6), bevel: 3.0, refraction: 4.0, grain: 0.5, frost: 0.7);
}

it('takes levels and paints a pane natively as ext-fb does, byte for byte', function (FormatSpec $spec): void {
    $make = fn (FramebufferDriver $d): array => [
        glassBackdrop($d->full(FormatSpec::rgba8(), 21, 15), 3), $d->full(FormatSpec::rgba8(), 11, 8), $d->full(FormatSpec::rgba8(), 6, 4),
        $d->full(FormatSpec::rgba8(), 11, 8), glassBackdrop($d->full($spec, 21, 15), 4),
    ];
    $out = [];
    foreach ([new NativeFramebufferDriver, new ExtendedFramebufferDriver] as $i => $driver) {
        [$capture, $half, $quarter, $up, $target] = $make($driver);
        $half->kawaseDown($capture, new Region(0, 0, 21, 15));
        $quarter->kawaseDown($half, new Region(0, 0, 11, 8));
        $up->kawaseUp($quarter);
        $target->paintGlass([2.3, 1.6, 19.4, 13.2, [5.0, 2.0, 0.0, 6.0]], $up, 2, $capture, 0, 0, [0.7, 4.0, 3.0, 1.4, 0.05, 0.5, 0x3366E64C, 0xFFFFFF99, 200], new Region(0, 0, 21, 15));
        $out[$i] = [bin2hex($half->dump()), bin2hex($quarter->dump()), bin2hex($up->dump()), bin2hex($target->dump())];
    }

    expect($out[0])->toBe($out[1]);
})->with([FormatSpec::rgba8(), Formats::rgb565()])->skip(! method_exists(FbBuffer::class, 'paintGlass'), 'ext-fb with glass is not loaded in this PHP.');

it('paints only inside the rounded box, and draws the same on every driver', function (FramebufferDriver $fb, RasterizeDriver $raster): void {
    $buffer = $fb->full(FormatSpec::rgba8(), 48, 32);
    $g = new VelvetGE($buffer, $raster, Edges::ANTIALIASED);
    $g->frame(function (RenderingEngine $g) use ($buffer): void {
        $g->clear(Color::hex('#204060'));
        $g->fillRect(0, 0, 24, 32, Color::hex('#e0c020'));
        $g->glass(6, 5, 36, 22, [8, 8, 8, 8], glassLook(), 0.9);
    });

    $px = fn (int $x, int $y): string => sprintf('%08x', $buffer->getPixel($x, $y));
    expect($px(2, 2))->toBe('e0c020ff')            // outside the box: the backdrop
        ->and($px(45, 30))->toBe('204060ff')
        ->and($px(6, 5))->toBe('e0c020ff')          // past the rounded corner
        ->and($px(24, 16))->not->toBe('204060ff');  // inside: the pane
    expect(bin2hex($buffer->dump()))->toBe(glassFrame());
})->with('framebuffer drivers')->with('rasterize drivers');

/** The reference frame of the test above, from the native drivers. */
function glassFrame(): string
{
    static $frame = null;
    if ($frame === null) {
        $buffer = (new NativeFramebufferDriver)->full(FormatSpec::rgba8(), 48, 32);
        (new VelvetGE($buffer, new Surface\Rasterize\Native\NativeRasterizeDriver, Edges::ANTIALIASED))->frame(function (RenderingEngine $g): void {
            $g->clear(Color::hex('#204060'));
            $g->fillRect(0, 0, 24, 32, Color::hex('#e0c020'));
            $g->glass(6, 5, 36, 22, [8, 8, 8, 8], glassLook(), 0.9);
        });
        $frame = bin2hex($buffer->dump());
    }

    return $frame;
}

it('records a pane in device pixels and redraws all it reads when anything under it changes', function (): void {
    $buffer = (new NativeFramebufferDriver)->dirty(FormatSpec::rgba8(), 160, 80);
    $g = new VelvetGE($buffer, new Surface\Rasterize\Native\NativeRasterizeDriver, Edges::ANTIALIASED);
    $draw = fn (float $dot) => function (RenderingEngine $g) use ($dot): void {
        $g->clear(Color::hex('#000000'));
        $g->fillRect($dot, 2, 2, 2, Color::hex('#ffffff'));
        $g->push();
        $g->scale(2.0);
        $g->glass(10, 6, 10, 6, [2, 2, 2, 2], new Glass(blur: 6.0, refraction: 1.5), 1.0);
        $g->pop();
    };
    $g->frame($draw(140.0));
    $g->frame($draw(150.0));  // a dot far from the pane: only it is redrawn
    $far = $g->damage();
    $g->frame($draw(16.0));  // a dot under the pane's read area: the whole area is redrawn
    $near = $g->damage();

    $margin = Glass::margin(12.0, 3.0);
    expect(array_sum(array_map(fn (Region $r): int => $r->width * $r->height, $far)))->toBeLessThan(64)
        ->and(array_filter($near, fn (Region $r): bool => $r->x <= max(0, 20 - $margin) && $r->right() >= min(160, 40 + $margin)))->not->toBe([]);
});

it('refuses a look out of range and a transform that turns', function (): void {
    expect(fn () => new Glass(blur: -1.0))->toThrow(DrawingException::class, 'A glass blur is 0..1024')
        ->and(fn () => new Glass(frost: 2.0))->toThrow(DrawingException::class, 'its frost 0..1');
    $g = new VelvetGE((new NativeFramebufferDriver)->full(FormatSpec::rgba8(), 8, 8), new Surface\Rasterize\Native\NativeRasterizeDriver);
    expect(fn () => $g->frame(function (RenderingEngine $g): void {
        $g->push();
        $g->rotate(0.3);
        $g->glass(1, 1, 4, 4, null, new Glass);
    }))->toThrow(DrawingException::class, 'glass() needs an upright transform');
});

/** @return list<array<int, mixed>> the commands VelvetGE is handed for a frame drawn by $draw, on a 240x120 surface */
function glassCommands(Closure $draw): array
{
    $g = new class((new NativeFramebufferDriver)->full(FormatSpec::rgba8(), 240, 120), new Surface\Rasterize\Native\NativeRasterizeDriver) extends VelvetGE {
        public array $kept = [];

        protected function execute(array $commands): void
        {
            $this->kept = $commands;
        }
    };
    $g->frame($draw);

    return [$g->kept, $g];
}

it('gives panes whose read areas nothing was drawn into one backdrop, and a pane drawn over by what came between its own', function (): void {
    $pane = fn (RenderingEngine $g, float $x) => $g->glass($x, 10, 24, 20, [4, 4, 4, 4], new Glass(blur: 2.0), 1.0);
    // two far-apart panes, nothing between them: one capture; a stripe over the second pane's area between them: two
    [$apart, $g] = glassCommands(function (RenderingEngine $g) use ($pane): void {
        $g->clear(Color::hex('#102030'));
        $pane($g, 4);
        $pane($g, 200);
    });
    [$between] = glassCommands(function (RenderingEngine $g) use ($pane): void {
        $g->clear(Color::hex('#102030'));
        $pane($g, 4);
        $g->fillRect(190, 0, 40, 60, Color::hex('#ff0000'));
        $pane($g, 200);
    });
    [$stacked] = glassCommands(function (RenderingEngine $g) use ($pane): void {
        $g->clear(Color::hex('#102030'));
        $pane($g, 4);
        $pane($g, 10);
    });
    $groups = fn (array $commands): array => array_map(fn (array $group): int => count($group['members']), Surface\Drawing\GlassGroups::of($commands, new Region(0, 0, 240, 120), $g->commandBox(...)));

    expect($groups($apart))->toBe([2])
        ->and($groups($between))->toBe([1, 1])
        ->and($groups($stacked))->toBe([1, 1])  // the second pane reads what the first painted
        ->and(array_map(fn (array $group): int => count($group['members']), Surface\Drawing\GlassGroups::of($apart, new Region(0, 0, 240, 120), null)))->toBe([1, 1]);  // no box: every pane its own
});

it('lowers each group to one backdrop and a draw a pane for a device that draws glass, and to backdrop layers for one that does not', function (): void {
    [$commands, $g] = glassCommands(function (RenderingEngine $g): void {
        $g->clear(Color::hex('#102030'));
        $g->glass(4, 10, 24, 20, [4, 4, 4, 4], new Glass(blur: 2.0), 1.0);
        $g->glass(200, 10, 24, 20, [4, 4, 4, 4], new Glass(blur: 2.0), 0.5);
        $g->fillRect(190, 0, 40, 60, Color::hex('#ff0000'));
        $g->glass(200, 40, 24, 20, null, new Glass(blur: 2.0), 1.0);
    });
    $ops = fn (bool $glass, ?Closure $box): array => array_map(fn (array $o): string => $o[0]->name, Surface\Drawing\Gpu\Lowering::lower($commands, 240, 120, null, $glass, box: $box)->operations);

    // the first two panes share a capture; the red stripe covers the third's read area: its own
    expect(array_count_values($ops(true, $g->commandBox(...))))->toMatchArray(['GLASS_BACKDROP' => 2, 'GLASS' => 3])
        ->and(array_count_values($ops(true, null))['GLASS_BACKDROP'])->toBe(3)
        ->and($ops(false, null))->not->toContain('GLASS')->toContain('BACKDROP')->toContain('FILTER');
});

it('blurs what was drawn between glass panes, as the pane before it did not', function (): void {
    // a stripe drawn after one pane and before the next, under the second: the second pane's backdrop holds it
    $draw = function (RenderingEngine $g): void {
        $g->clear(Color::hex('#000000'));
        $g->glass(4, 10, 24, 20, [4, 4, 4, 4], new Glass(blur: 4.0), 1.0);
        $g->fillRect(100, 0, 40, 120, Color::hex('#ffffff'));
        $g->glass(104, 10, 24, 20, [4, 4, 4, 4], new Glass(blur: 4.0), 1.0);
    };
    $buffer = (new NativeFramebufferDriver)->full(FormatSpec::rgba8(), 240, 120);
    (new VelvetGE($buffer, new Surface\Rasterize\Native\NativeRasterizeDriver, Edges::ANTIALIASED))->frame($draw);

    // the second pane is over a white stripe: it is light, not the black of the frame's start
    expect($buffer->getPixel(116, 20) >> 24)->toBeGreaterThan(200);
});

it('marks a glass backdrop kept only when its region and depths match last frame\'s and no damage touches it', function (): void {
    $g = new class((new NativeFramebufferDriver)->full(FormatSpec::rgba8(), 160, 80), new Surface\Rasterize\Native\NativeRasterizeDriver) extends VelvetGE {
        public array $kept = [];

        protected function execute(array $commands): void
        {
            $this->kept = $commands;
        }
    };
    $g->frame(function (RenderingEngine $g): void {
        $g->clear(Color::hex('#102030'));
        $g->glass(10, 10, 30, 20, null, new Glass(blur: 6.0), 1.0);
    });
    $commands = $g->kept;
    $backdrops = [];
    $keepOf = function (array $damage, array $commands) use (&$backdrops): bool {
        $ops = Surface\Drawing\Gpu\Lowering::lower($commands, 160, 80, null, true, $backdrops, $damage)->operations;

        return array_values(array_filter($ops, fn (array $o): bool => $o[0]->name === 'GLASS_BACKDROP'))[0][5];
    };

    expect($keepOf([new Region(0, 0, 160, 80)], $commands))->toBeFalse()               // the first frame: nothing to keep
        ->and($keepOf([new Region(140, 60, 10, 10)], $commands))->toBeTrue()            // damage far from the region
        ->and($keepOf([new Region(12, 12, 4, 4)], $commands))->toBeFalse()              // damage under it
        ->and($keepOf([], $commands))->toBeTrue()                                       // nothing redrawn at all
        ->and($keepOf([], [['glass', new Region(60, 10, 30, 20), [60.0, 10.0, 90.0, 30.0, [0.0, 0.0, 0.0, 0.0]], $commands[1][3], 6.0, null, new Region(60, 10, 30, 20)]]))->toBeFalse(); // a pane that moved
});

it('shows the sharp backdrop for a blur under a pixel, and takes the widest blur a slider sets at any scale', function (): void {
    $frame = function (float $blur, float $scale): VelvetGE {
        $g = new VelvetGE((new NativeFramebufferDriver)->full(FormatSpec::rgba8(), 120, 60), new Surface\Rasterize\Native\NativeRasterizeDriver, Edges::HARD);
        $g->frame(function (RenderingEngine $g) use ($blur, $scale): void {
            $g->clear(Color::hex('#000000'));
            $g->fillRect(0, 0, 60, 60, Color::hex('#ffffff'));
            $g->push();
            $g->scale($scale);
            $g->glass(20 / $scale, 10 / $scale, 80 / $scale, 40 / $scale, null, new Glass(blur: $blur), 1.0);
            $g->pop();
        });

        return $g;
    };
    // blur 0: the white/black edge at x = 60 stays an edge through the pane, one pixel to either side of it
    $sharp = $frame(0.0, 1.0)->framebuffer();
    expect($sharp->getPixel(59, 30) >> 24)->toBe(255)
        ->and($sharp->getPixel(60, 30) >> 24)->toBe(0);
    // blur 20: the edge is a ramp
    $soft = $frame(20.0, 1.0)->framebuffer();
    expect($soft->getPixel(59, 30) >> 24)->toBeLessThan(250)->and($soft->getPixel(60, 30) >> 24)->toBeGreaterThan(5);
    // the widest the gallery's slider sets (96 logical) at scale 3 is past the old limit of 256 device pixels
    expect(fn () => $frame(96.0 * 3, 1.0))->not->toThrow(DrawingException::class);
});

it('paints a pane\'s shadow after reading the backdrop and before the pane: the pane is not darkened by it, and its rounded corners meet the shadow', function (): void {
    $frame = function (?Color $shadow, array $radii = [14, 14, 14, 14]): Surface\Contracts\Framebuffers\Framebuffer {
        $buffer = (new NativeFramebufferDriver)->full(FormatSpec::rgba8(), 160, 100);
        (new VelvetGE($buffer, new Surface\Rasterize\Native\NativeRasterizeDriver, Edges::ANTIALIASED))->frame(function (RenderingEngine $g) use ($shadow, $radii): void {
            $g->clear(Color::hex('#808080'));
            $g->glass(40, 30, 80, 40, $radii, new Glass(blur: 0.0, shadow: 12.0, shadowColor: $shadow), 1.0);
        });

        return $buffer;
    };
    $with = $frame(Color::rgba(0, 0, 0, 0.8));
    $without = $frame(null);

    // inside the pane: the sharp backdrop, grey, shadow or none
    expect($with->getPixel(80, 50))->toBe($without->getPixel(80, 50))
        // outside the rounded corner but inside the rectangle (the box's top-left, 40,30): shadowed, darker than the bare backdrop
        ->and($with->getPixel(41, 31) >> 24)->toBeLessThan($without->getPixel(41, 31) >> 24)
        // and the shadow is there past the box too
        ->and($with->getPixel(34, 50) >> 24)->toBeLessThan(0x80);
});

it('keeps a pane\'s shadow in a partial frame: a region that only reaches the shadow gets it, and one over the pane both', function (): void {
    $draw = fn (int $dot) => function (RenderingEngine $g) use ($dot): void {
        $g->clear(Color::hex('#808080'));
        $g->fillRect($dot, 0, 2, 2, Color::hex('#ffffff'));
        $g->glass(60, 30, 40, 30, [8, 8, 8, 8], new Glass(blur: 0.0, shadow: 10.0, shadowColor: Color::rgba(0, 0, 0, 0.8)), 1.0);
    };
    $make = fn (): array => [$b = (new NativeFramebufferDriver)->dirty(FormatSpec::rgba8(), 160, 100), new VelvetGE($b, new Surface\Rasterize\Native\NativeRasterizeDriver, Edges::HARD)];
    [$partial, $a] = $make();
    [$whole, $b] = $make();
    $a->frame($draw(2));
    $a->frame($draw(3));   // a pixel moves far from the pane: a partial frame
    $b->frame($draw(3));

    expect(bin2hex($partial->dump()))->toBe(bin2hex($whole->dump()));
    // a dot at the pane's shadow fringe: the region reaches the shadow only
    [$fringe, $c] = $make();
    [$fringeWhole, $d] = $make();
    $c->frame($draw(2));
    $c->frame($draw(52));
    $d->frame($draw(52));

    expect(bin2hex($fringe->dump()))->toBe(bin2hex($fringeWhole->dump()));
});
