<?php

use Surface\Contracts\Drawing\CPUEngine;
use Surface\Contracts\Drawing\CPUHost;
use Surface\Contracts\Drawing\Drawing2D;
use Surface\Contracts\Drawing\PagedDrawTarget;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\ChannelPalette;
use Surface\Contracts\Framebuffers\ChannelSpec;
use Surface\Contracts\Framebuffers\EInkColor;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Drawing\Canvases\DirtyCanvas;
use Surface\Drawing\Canvases\EPaperCanvas;
use Surface\Drawing\Canvases\FullCanvas;
use Surface\Drawing\Canvases\NFramesCanvas;
use Surface\Drawing\Canvases\PagedCanvas;
use Surface\Drawing\Engines\DirtyEngine;
use Surface\Drawing\Engines\EPaperEngine;
use Surface\Drawing\Engines\FullEngine;
use Surface\Drawing\Engines\NFramesEngine;
use Surface\Drawing\Engines\PagedEngine;
use Surface\Framebuffers\FramebufferManager;
use Venusian\Surface\Tests\Support\Fakes\FakeBindingVessel;
use Venusian\Surface\Tests\Support\Fakes\FakeConfigRepository;
use Venusian\Surface\Tests\Support\Fakes\FakeExecutor;

function framebuffers(): FramebufferManager
{
    return new FramebufferManager(new FakeBindingVessel(['config' => new FakeConfigRepository(['framebuffers' => []])]));
}

it('each engine names itself and mints its canvas through the framebuffer driver', function () {
    $fb = framebuffers();
    $mono = new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1);
    $planar = new FormatSpec(PixelFormat::PLANAR, BitDepth::B1, palette: new ChannelPalette(new ChannelSpec(EInkColor::BLACK->value)));

    expect((new DirtyEngine($fb))->engine())->toBe(CPUEngine::DIRTY)
        ->and((new DirtyEngine($fb))->attach(new CPUHost(8, 8, $mono)))->toBeInstanceOf(DirtyCanvas::class)
        ->and((new FullEngine($fb))->attach(new CPUHost(8, 8, $mono)))->toBeInstanceOf(FullCanvas::class)
        ->and((new EPaperEngine($fb))->attach(new CPUHost(8, 8, $planar)))->toBeInstanceOf(EPaperCanvas::class)
        ->and((new PagedEngine($fb))->attach(new CPUHost(8, 16, $mono, page_rows: 8)))->toBeInstanceOf(PagedCanvas::class)
        ->and((new PagedEngine($fb))->attach(new CPUHost(8, 16, $mono)))->toBeInstanceOf(PagedDrawTarget::class)
        ->and((new NFramesEngine($fb))->attach(new CPUHost(8, 8, $mono, frames: 3)))->toBeInstanceOf(NFramesCanvas::class)
        ->and((new NFramesEngine($fb))->engine())->toBe(CPUEngine::NFRAMES);
});

it('the spec\'s goal snippet runs end to end, and rgba8 feeds a GPU texture', function () {
    $canvas = (new DirtyEngine(framebuffers()))->attach(new CPUHost(128, 64, new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1)));
    $canvas->onDraw(fn (Drawing2D $g) => $g->fillCircle(64.0, 32.0, 20.0, Color::hex('#fff')));
    $canvas->renderFrame();

    $sent = [];
    foreach ($canvas->damage() as $region) {
        $sent[] = [$region->x, $region->y, count($canvas->flushRegion($region, as_array: true)), $region->width, $region->height];
    }
    // the disc covers rows 12..51 → pages 1..6 → one region y 8..55, 128 columns × 6 pages of bytes
    expect($sent)->toBe([[0, 8, 128 * 6, 128, 48]]);

    $executor = new FakeExecutor();
    $handle = $executor->texture($canvas->rgba8(), 128, 64);
    expect(strlen($executor->textures[$handle->id][0]))->toBe(128 * 64 * 4);
});

it('an engine accepts a driver directly, for a consumer with no container', function () {
    $engine = new DirtyEngine(new \Surface\Framebuffers\Php\PhpFramebufferDriver());

    expect($engine->attach(new CPUHost(8, 8, new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1))))
        ->toBeInstanceOf(DirtyCanvas::class);
});
