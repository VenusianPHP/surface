<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Drawing\OutputTarget;
use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Drawing\SurfaceKind;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\BitOrder;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Framebuffers\ScanDirection;
use Surface\Contracts\Rasterize\Edges;
use Surface\Drawing\Gpu\GpuRenderingEngine;
use Surface\Drawing\Gpu\Op;
use Surface\EmbeddedDisplays\EmbeddedDisplay;
use Surface\Fonts\ClassicFont;
use Surface\Framebuffers\Native\NativeFullFramebuffer;
use Surface\NutsAndBolts\Color;
use Venusian\Surface\Tests\Fixtures\FakeCanvas;
use Venusian\Surface\Tests\Fixtures\FakeHost;

/*
 * The GPU engine, engine-neutral, over FakeGpuDevice: frames lowered and
 * handed to the device, damage recorded on the engine's framebuffer, a canvas
 * borrowed from, a display bound to.
 */

/** A frame the fake device can carry out in full: a clear and whole-pixel text. */
function tick(int $i): Closure
{
    return function (RenderingEngine $g) use ($i): void {
        $g->clear(Color::rgb(0, 0, 0));
        $g->text('ab', 2, 2, Color::rgb(255, 255, 255), new ClassicFont);
        $g->text(sprintf('%02d', $i), 30, 12, Color::rgb(255, 255, 0), new ClassicFont);
    };
}

function gpuCanvas(SurfaceKind ...$lends): FakeCanvas
{
    $canvas = (new FakeHost('main'))->column('m')->canvas('view');
    $canvas->lends = $lends;

    return $canvas;
}

function monoDisplay(): EmbeddedDisplay
{
    $mono = new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1, ScanDirection::TOP_TO_BOTTOM, BitOrder::MSB_FIRST);

    return new EmbeddedDisplay('ink', new FakeWindowPanel(16, 8, $mono), framebuffers(), ['refreshing' => 'epaper', 'addressable' => 'dirty', 'whole' => 'full']);
}

it('is named by its device and draws into the device\'s target', function () {
    $device = new FakeGpuDevice(name: 'metal');
    $engine = new GpuRenderingEngine($device, 64, 32);

    expect($engine->name())->toBe('metal')
        ->and($engine->framebuffer())->toBe($device->target)
        ->and([$engine->width(), $engine->height()])->toBe([64, 32])
        ->and($engine->device())->toBe($device)
        ->and($engine)->toBeInstanceOf(RenderingEngine::class);
});

it('asks for four samples with anti-aliased edges and one with hard edges', function () {
    $smooth = new FakeGpuDevice;
    $hard = new FakeGpuDevice;

    expect((new GpuRenderingEngine($smooth, 64, 32))->edges())->toBe(Edges::ANTIALIASED)
        ->and($smooth->log)->toBe(['target 64x32x4'])
        ->and((new GpuRenderingEngine($hard, 64, 32, Edges::HARD))->edges())->toBe(Edges::HARD)
        ->and($hard->log)->toBe(['target 64x32x1']);
});

it('hands the device one lowered list a frame and records the frame\'s damage on its framebuffer', function () {
    $device = new FakeGpuDevice;
    $engine = new GpuRenderingEngine($device, 64, 32);

    $engine->frame(tick(0));

    expect($device->lists)->toHaveCount(1)
        ->and($device->lists[0]->operations[0])->toBe([Op::CLEAR, 0x000000FF])
        ->and([$device->lists[0]->width, $device->lists[0]->height])->toBe([64, 32])
        ->and($engine->damage())->toEqual([new Region(0, 0, 64, 32)])
        ->and($engine->framebuffer()->damage())->toEqual([new Region(0, 0, 64, 32)]);
});

it('draws only what changed: scissored, a solid quad for the clear, the damage on the framebuffer', function () {
    $device = new FakeGpuDevice;
    $engine = new GpuRenderingEngine($device, 64, 32);
    $engine->frame(tick(0));
    $engine->framebuffer()->beginEpoch();

    $engine->frame(tick(1));

    $kinds = array_column($device->lists[1]->operations, 0);
    $area = array_sum(array_map(fn (Region $r): int => $r->width * $r->height, $engine->damage()));
    expect($kinds)->not->toContain(Op::CLEAR)
        ->and($kinds)->toContain(Op::SOLID)
        ->and($device->lists[1]->operations[0])->toEqual([Op::SCISSOR, $engine->damage()[0]])
        ->and($area)->toBeGreaterThan(0)->toBeLessThan(64 * 32)
        ->and($engine->framebuffer()->damage())->toEqual($engine->damage());
});

it('draws nothing for a frame identical to the last', function () {
    $device = new FakeGpuDevice;
    $engine = new GpuRenderingEngine($device, 64, 32);
    $engine->frame(tick(3));
    $engine->framebuffer()->beginEpoch();

    $engine->frame(tick(3));

    expect($device->lists)->toHaveCount(1)
        ->and($engine->framebuffer()->damage())->toBe([]);
});

it('draws partial frames byte for byte as whole redraws would', function () {
    $partial = new GpuRenderingEngine(new FakeGpuDevice, 64, 32);
    $whole = new GpuRenderingEngine(new FakeGpuDevice, 64, 32);

    foreach (range(0, 4) as $i) {
        $partial->frame(tick($i));
        $whole->invalidate()->frame(tick($i));

        expect($partial->framebuffer()->toRgba8())->toBe($whole->framebuffer()->toRgba8(), "frame {$i}");
    }
    expect($partial->framebuffer()->toRgba8())->not->toBe(str_repeat("\x00\x00\x00\xff", 64 * 32));
});

it('redraws the last frame on replay()', function () {
    $device = new FakeGpuDevice;
    $engine = new GpuRenderingEngine($device, 64, 32);
    $engine->frame(tick(0));

    $engine->replay();

    expect($device->lists)->toHaveCount(2)
        ->and($device->lists[1]->operations)->toEqual($device->lists[0]->operations);
});

it('borrows the first surface kind its device presents into that the canvas lends, then makes its target', function () {
    $canvas = gpuCanvas(SurfaceKind::GL_CONTEXT, SurfaceKind::METAL_LAYER);
    $device = new FakeGpuDevice([SurfaceKind::VULKAN_SURFACE, SurfaceKind::METAL_LAYER, SurfaceKind::GL_CONTEXT], ['instance' => 0xABC]);

    $engine = new GpuRenderingEngine($device, 80, 60, null, $canvas);

    expect($canvas->lent()?->kind)->toBe(SurfaceKind::METAL_LAYER)
        ->and($canvas->log)->toContain('surface:metal-layer:{"instance":2748}')
        ->and($device->log)->toBe(['adopt', 'target 80x60x4'])
        ->and($canvas->boundFramebuffer())->toBe($engine->framebuffer());
});

it('presents on the canvas through its device, into the lent surface', function () {
    $canvas = gpuCanvas(SurfaceKind::GL_CONTEXT);
    $device = new FakeGpuDevice([SurfaceKind::GL_CONTEXT]);
    $engine = new GpuRenderingEngine($device, 80, 60, null, $canvas);

    $engine->frame(tick(0));
    $canvas->present();
    $canvas->present();

    expect($device->log)->toBe(['adopt', 'target 80x60x4', 'draw', 'present'])
        ->and($engine->framebuffer()->damage())->toBe([]);
});

it('refuses a canvas that lends nothing its device presents into, naming the engines the canvas can host', function () {
    new GpuRenderingEngine(new FakeGpuDevice([SurfaceKind::METAL_LAYER], name: 'metal'), 80, 60, null, gpuCanvas(SurfaceKind::GL_CONTEXT, SurfaceKind::DMABUF));
})->throws(DrawingException::class, "This window cannot host 'metal': it lends gl-context, dmabuf. It can host: velvet, opengl, vulkan.");

it('refuses a canvas that lends nothing at all', function () {
    new GpuRenderingEngine(new FakeGpuDevice([SurfaceKind::METAL_LAYER], name: 'metal'), 80, 60, null, gpuCanvas());
})->throws(DrawingException::class, "This window cannot host 'metal': it lends no surface. It can host: velvet.");

it('gives the surface back when its target cannot be made', function () {
    $canvas = gpuCanvas(SurfaceKind::GL_CONTEXT);
    $device = new class([SurfaceKind::GL_CONTEXT]) extends FakeGpuDeviceThatFails {};

    expect(fn () => new GpuRenderingEngine($device, 80, 60, null, $canvas))->toThrow(RuntimeException::class, 'no memory')
        ->and($canvas->lent())->toBeNull();
});

it('re-makes its target when the canvas changes size, and draws the next frame whole', function () {
    $canvas = gpuCanvas(SurfaceKind::GL_CONTEXT);
    $device = new FakeGpuDevice([SurfaceKind::GL_CONTEXT]);
    $engine = new GpuRenderingEngine($device, 80, 60, null, $canvas);
    $engine->frame(tick(0));
    $first = $engine->framebuffer();

    $canvas->measures = [50, 30];
    $engine->frame(tick(0));

    expect($device->log)->toBe(['adopt', 'target 80x60x4', 'draw', 'target 100x60x4', 'draw'])
        ->and($engine->framebuffer())->not->toBe($first)
        ->and([$engine->width(), $engine->height()])->toBe([100, 60])
        ->and($engine->damage())->toEqual([new Region(0, 0, 100, 60)])
        ->and($canvas->boundFramebuffer())->toBe($engine->framebuffer());
});

it('keeps its target while the canvas has no size', function () {
    $canvas = gpuCanvas(SurfaceKind::GL_CONTEXT);
    $device = new FakeGpuDevice([SurfaceKind::GL_CONTEXT]);
    $engine = new GpuRenderingEngine($device, 80, 60, null, $canvas);

    $canvas->measures = [0, 0];
    $engine->frame(tick(0));

    expect($device->log)->toBe(['adopt', 'target 80x60x4', 'draw']);
});

it('keeps drawing offscreen after the canvas reclaims its surface', function () {
    $canvas = gpuCanvas(SurfaceKind::GL_CONTEXT);
    $device = new FakeGpuDevice([SurfaceKind::GL_CONTEXT]);
    $engine = new GpuRenderingEngine($device, 80, 60, null, $canvas);

    $canvas->remove();
    $engine->frame(tick(0));

    expect($device->lists)->toHaveCount(1)
        ->and($device->log)->not->toContain('present');
});

it('binds its framebuffer to a display, which then sends what changed in the panel\'s format', function () {
    $display = fakeDisplay();
    $engine = new GpuRenderingEngine(new FakeGpuDevice, 16, 8, null, $display);
    $draw = fn (int $x) => function (RenderingEngine $g) use ($x): void {
        $g->clear(Color::rgb(0, 0, 0));
        $g->text('a', $x, 0, Color::rgb(255, 255, 255), new ClassicFont);
    };

    $engine->frame($draw(0));
    $display->present();
    $engine->frame($draw(8));
    $display->present();

    $windows = $display->panel()->windows();
    $sent = $display->panel()->calls[1];
    $region = new Region($sent[1], $sent[2], $sent[3], $sent[4]);
    $resent = array_sum(array_map(fn (array $window): int => $window[2] * $window[3], array_slice($windows, 1)));
    expect($display->boundFramebuffer())->toBe($engine->framebuffer())
        ->and($windows[0])->toBe([0, 0, 16, 8])
        ->and($resent)->toBeGreaterThan(0)->toBeLessThan(16 * 8)
        ->and($sent[5])->toBe($engine->framebuffer()->flushRegion($region, rgb565(), true))
        ->and($engine->framebuffer()->damage())->toBe([]);
});

it('picks hard edges for a panel whose format cannot blend, anti-aliased otherwise', function () {
    expect((new GpuRenderingEngine(new FakeGpuDevice, 16, 8, null, monoDisplay()))->edges())->toBe(Edges::HARD)
        ->and((new GpuRenderingEngine(new FakeGpuDevice, 16, 8, null, fakeDisplay()))->edges())->toBe(Edges::ANTIALIASED)
        ->and((new GpuRenderingEngine(new FakeGpuDevice([SurfaceKind::GL_CONTEXT]), 80, 60, null, gpuCanvas(SurfaceKind::GL_CONTEXT)))->edges())->toBe(Edges::ANTIALIASED)
        ->and((new GpuRenderingEngine(new FakeGpuDevice, 16, 8, Edges::ANTIALIASED, monoDisplay()))->edges())->toBe(Edges::ANTIALIASED);
});

it('refuses an output that is neither a window nor a display', function () {
    $other = new class implements OutputTarget {
        public function boundFramebuffer(): ?Framebuffer { return null; }

        public function framebuffer(): Framebuffer { return new NativeFullFramebuffer(FormatSpec::rgba8(), 1, 1); }

        public function present(): static { return $this; }

        public function pixelSize(): array { return [1, 1]; }

        public function pixelFormat(): FormatSpec { return FormatSpec::rgba8(); }
    };

    expect(fn () => new GpuRenderingEngine(new FakeGpuDevice, 1, 1, null, $other))
        ->toThrow(DrawingException::class, 'A GPU engine draws for a window (a WindowOutput), for a display (an EmbeddedDisplay), or for no output; got Surface\Contracts\Drawing\OutputTarget@anonymous');
});

it('reclaims its surface and releases its device on release()', function () {
    $canvas = gpuCanvas(SurfaceKind::GL_CONTEXT);
    $device = new FakeGpuDevice([SurfaceKind::GL_CONTEXT]);
    $engine = new GpuRenderingEngine($device, 80, 60, null, $canvas);

    $engine->release();

    expect($canvas->lent())->toBeNull()
        ->and($device->log)->toBe(['adopt', 'target 80x60x4', 'release']);
});

it('is built from the shared arguments', function () {
    $offscreen = GpuRenderingEngine::from(new FakeGpuDevice, ['width' => 64, 'height' => 32, 'edges' => 'hard'], drawing());
    $display = fakeDisplay();
    $on_display = GpuRenderingEngine::from(new FakeGpuDevice, ['output' => $display], drawing());
    $canvas = gpuCanvas(SurfaceKind::GL_CONTEXT);
    $on_canvas = GpuRenderingEngine::from(new FakeGpuDevice([SurfaceKind::GL_CONTEXT]), ['output' => $canvas, 'edges' => Edges::HARD], drawing());

    expect([$offscreen->width(), $offscreen->height(), $offscreen->edges()])->toBe([64, 32, Edges::HARD])
        ->and([$on_display->width(), $on_display->height()])->toBe([16, 8])
        ->and($display->boundFramebuffer())->toBe($on_display->framebuffer())
        ->and([$on_canvas->width(), $on_canvas->height(), $on_canvas->edges()])->toBe([80, 60, Edges::HARD])
        ->and($canvas->lent())->not->toBeNull();
});

it('refuses arguments it cannot be built from, before touching the device', function (Closure $args, string $message) {
    $device = new FakeGpuDevice(name: 'metal');

    expect(fn () => GpuRenderingEngine::from($device, $args(), drawing()))->toThrow(DrawingException::class, $message)
        ->and($device->log)->toBe([]);
})->with([
    'a framebuffer' => [fn (): array => ['framebuffer' => new NativeFullFramebuffer(FormatSpec::rgba8(), 2, 2)], "metal draws into its own framebuffer and does not take one: read it from the engine's framebuffer()."],
    'a misspelt argument' => [fn (): array => ['width' => 8, 'hieght' => 8], "metal does not take 'hieght'. It takes: output, width, height, edges."],
    'a Velvet argument' => [fn (): array => ['width' => 8, 'height' => 8, 'mode' => 'ring'], "metal does not take 'mode'. It takes: output, width, height, edges."],
    'nothing to size from' => [fn (): array => [], "metal needs an 'output', or a 'width' and a 'height'."],
    'a width alone' => [fn (): array => ['width' => 8], "metal needs an 'output', or a 'width' and a 'height'."],
    'a size that is not whole numbers' => [fn (): array => ['width' => 8.5, 'height' => 8], "'width' and 'height' are integers."],
    'no size at all' => [fn (): array => ['width' => 0, 'height' => 8], "'width' and 'height' are at least 1."],
    'an output and a size' => [fn (): array => ['output' => fakeDisplay(), 'width' => 8], "'output' comes alone: 'width' describes a framebuffer to be made."],
    'an output that is not one' => [fn (): array => ['output' => 'canvas'], "'output' is an OutputTarget: a canvas or a display."],
    'a canvas with no size yet' => [function (): array {
        $canvas = gpuCanvas(SurfaceKind::GL_CONTEXT);
        $canvas->measures = [0, 0];

        return ['output' => $canvas];
    }, 'The output has no size yet: show its window first.'],
    'edges that are not edges' => [fn (): array => ['width' => 8, 'height' => 8, 'edges' => 'soft'], "'edges' is 'hard' or 'antialiased', got 'soft'."],
]);

it('records the whole surface as drawn when it replays', function () {
    $engine = new GpuRenderingEngine(new FakeGpuDevice, 64, 32);
    $engine->frame(tick(0));
    $engine->frame(tick(1));
    $engine->framebuffer()->beginEpoch();

    $engine->replay();

    expect($engine->framebuffer()->damage())->toEqual([new Region(0, 0, 64, 32)]);
});

it('releases its device when the display refuses its framebuffer', function () {
    $mono = new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1, ScanDirection::TOP_TO_BOTTOM, BitOrder::MSB_FIRST);
    $display = new Surface\EmbeddedDisplays\DirectEDisplay('tft', new FakePipePanel(16, 8, $mono), framebuffers(), ['refreshing' => 'epaper', 'addressable' => 'dirty', 'whole' => 'full']);
    $device = new FakeGpuDevice;

    expect(fn () => new GpuRenderingEngine($device, 16, 8, null, $display))->toThrow(Surface\Contracts\EmbeddedDisplays\EmbeddedDisplayException::class)
        ->and($device->log)->toBe(['target 16x8x1', 'release']);
});
