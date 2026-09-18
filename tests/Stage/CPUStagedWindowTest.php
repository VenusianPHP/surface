<?php

use Surface\Contracts\Drawing\CPUEngine;
use Surface\Contracts\Drawing\CPUHost;
use Surface\Contracts\Drawing\Drawing2D;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Contracts\Stage\Events\StageClosed;
use Surface\Contracts\Stage\Events\StageResized;
use Surface\Contracts\Stage\StageException;
use Surface\Contracts\Stage\StageFit;
use Surface\Drawing\Engines\DirtyEngine;
use Surface\Framebuffers\FramebufferManager;
use Venusian\Surface\Tests\Support\Fakes\FakeBindingVessel;
use Venusian\Surface\Tests\Support\Fakes\FakeConfigRepository;
use Venusian\Surface\Tests\Support\Fakes\FakeCPUStagedWindow;

/** A 128x64 SSD1306 canvas in a 512x256 window, 4x. @return array{FakeCPUStagedWindow, \Voyager\IOPools\IOPoolDock} */
function cpuStage(StageFit $fit = StageFit::INTEGER_SCALE): array
{
    $framebuffers = new FramebufferManager(new FakeBindingVessel(['config' => new FakeConfigRepository(['framebuffers' => []])]));
    $canvas = (new DirtyEngine($framebuffers))->attach(new CPUHost(128, 64, new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1)));
    $dock = bareDock();
    $stage = (new FakeCPUStagedWindow('oled', $canvas, 512, 256, 1.0, $fit))->setPool($dock);

    return [$stage, $dock];
}

it('knows the window and the canvas apart', function () {
    [$stage] = cpuStage();

    expect($stage->name())->toBe('oled')
        ->and($stage->size())->toBe([512, 256])
        ->and($stage->canvasSize())->toBe([128, 64])
        ->and($stage->drawableSize())->toBe([128, 64])
        ->and($stage->fit())->toBe(StageFit::INTEGER_SCALE)
        ->and($stage->engine())->toBe(CPUEngine::DIRTY)
        ->and($stage->hostFormat()->pixel_format)->toBe(PixelFormat::MONO_VERTICAL_PAGE)
        ->and($stage->isOpen())->toBeTrue();
});

it('renders nothing and presents nothing until shown', function () {
    [$stage] = cpuStage();
    $ran = 0;
    $stage->onDraw(function (Drawing2D $g) use (&$ran) { $ran++; $g->fillRect(0.0, 0.0, 8.0, 8.0, Color::hex('#fff')); });

    expect($stage->renderFrame())->toBeFalse()
        ->and($ran)->toBe(0)
        ->and($stage->presents)->toBe([]);
});

it('presents the canvas after a frame that ran', function () {
    [$stage] = cpuStage();
    $stage->onDraw(fn (Drawing2D $g) => $g->fillRect(0.0, 0.0, 8.0, 8.0, Color::hex('#fff')))->show();
    $stage->presents = [];

    expect($stage->renderFrame())->toBeTrue()
        ->and($stage->presents)->toHaveCount(1)
        ->and(strlen($stage->presents[0]))->toBe(128 * 64 * 4);
});

it('show() paints once without running the hook', function () {
    [$stage] = cpuStage();
    $ran = 0;
    $stage->onDraw(function () use (&$ran) { $ran++; });
    $stage->show();

    expect($stage->presents)->toHaveCount(1)
        ->and($ran)->toBe(0)
        ->and($stage->log)->toBe(['show']);
});

it('a resize re-presents the same canvas and mails once, leaving the canvas size alone', function () {
    [$stage, $dock] = cpuStage();
    $stage->onDraw(fn () => null)->show();
    $stage->presents = [];

    $stage->resized(800, 600, 2.0);
    $stage->resized(800, 600, 2.0);

    expect($stage->presents)->toHaveCount(1)
        ->and($stage->size())->toBe([800, 600])
        ->and($stage->scale())->toBe(2.0)
        ->and($stage->canvasSize())->toBe([128, 64])
        ->and($dock->drain()->filter(fn ($m) => $m instanceof StageResized))->toHaveCount(1);
});

it('an on-demand stage presents once per redraw()', function () {
    [$stage] = cpuStage();
    $stage->setContinuous(false)->onDraw(fn () => null)->show();
    $stage->presents = [];

    expect($stage->renderFrame())->toBeFalse();
    $stage->redraw();
    expect($stage->renderFrame())->toBeTrue()
        ->and($stage->renderFrame())->toBeFalse()
        ->and($stage->presents)->toHaveCount(1);
});

it('delegates every CPU verb to the canvas', function () {
    [$stage] = cpuStage();
    $stage->onDraw(fn (Drawing2D $g) => $g->fillRect(0.0, 0.0, 128.0, 8.0, Color::hex('#fff')))->show()->renderFrame();

    expect(bin2hex($stage->flush()))->toStartWith('ffff')
        ->and($stage->flush(as_array: true))->toHaveCount(128 * 8)
        ->and($stage->damage())->toEqual([new Region(0, 0, 128, 8)])
        ->and(bin2hex($stage->flushRegion(new Region(0, 0, 128, 8))))->toBe(str_repeat('ff', 128))
        ->and(strlen($stage->rgba8()))->toBe(128 * 64 * 4)
        ->and($stage->drawing())->toBeInstanceOf(\Surface\Drawing\Rasterizer::class);
});

it('close() releases the presenter, then the native, then announces once', function () {
    [$stage, $dock] = cpuStage();
    $stage->show();
    $stage->close();
    $stage->close();

    expect($stage->log)->toBe(['show', 'releaseEngine', 'destroyNative'])
        ->and($stage->isOpen())->toBeFalse()
        ->and($dock->drain()->filter(fn ($m) => $m instanceof StageClosed))->toHaveCount(1);
});

it('a release that throws still destroys the native and announces', function () {
    [$stage, $dock] = cpuStage();
    $stage->release_failure = new RuntimeException('renderer gone');

    expect(fn () => $stage->close())->toThrow(RuntimeException::class, 'renderer gone')
        ->and($stage->log)->toBe(['releaseEngine', 'destroyNative'])
        ->and($dock->drain()->filter(fn ($m) => $m instanceof StageClosed))->toHaveCount(1);
});

it('a closed stage refuses a title and renders nothing', function () {
    [$stage] = cpuStage();
    $stage->close();

    expect(fn () => $stage->setTitle('x'))->toThrow(StageException::class)
        ->and($stage->renderFrame())->toBeFalse();
});
