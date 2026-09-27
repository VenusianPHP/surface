<?php

use Surface\Canvas\Canvas;
use Surface\Contracts\Canvas\CanvasException;
use Surface\Contracts\Canvas\CanvasKind;
use Surface\Contracts\Drawing\Color;
use Surface\Contracts\Drawing\Drawing2D;
use Surface\Contracts\Drawing\Frame;
use Surface\Contracts\Drawing\GPUEngine;
use Surface\Contracts\Stage\StageFit;
use Surface\EmbeddedDisplays\EmbeddedDisplay;
use Venusian\Surface\Tests\Support\Fakes\FakeCPUStagedWindow;
use Venusian\Surface\Tests\Support\Fakes\FakeDisplayPanel;
use Venusian\Surface\Tests\Support\Fakes\FakeExecutor;
use Venusian\Surface\Tests\Support\Fakes\FakeStagedWindow;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

/** A canvas over a shown GPU stage. @return array{Canvas, FakeStagedWindow, FakeExecutor} */
function gpuStageCanvas(): array
{
    $executor = new FakeExecutor();
    $stage = (new FakeStagedWindow('main', GPUEngine::METAL, $executor, 64, 48))->setPool(bareDock())->show();

    return [Canvas::of($stage), $stage, $executor];
}

/** A canvas over a 16x16 OLED on a real dirty canvas. @return array{Canvas, EmbeddedDisplay, FakeDisplayPanel} */
function displayCanvas(): array
{
    $panel = new FakeDisplayPanel(16, 16, oledSpec());
    $display = new EmbeddedDisplay('oled', $panel, panelCanvas(16, 16, oledSpec()));

    return [Canvas::of($display), $display, $panel];
}

it('knows what it wraps, and refuses what it cannot manage', function () {
    [$stage] = gpuStageCanvas();
    [$display] = displayCanvas();
    $cpu_stage = Canvas::of(new FakeCPUStagedWindow('emu', panelCanvas(16, 16, oledSpec()), 64, 64, 1.0, StageFit::INTEGER_SCALE));
    $view = Canvas::of((new FakeWindow('main'))->gpu('scene', 'metal', 0, 0, 64, 48));

    expect($stage->kind())->toBe(CanvasKind::GPU_STAGE)
        ->and($cpu_stage->kind())->toBe(CanvasKind::CPU_STAGE)
        ->and($view->kind())->toBe(CanvasKind::GPU_VIEW)
        ->and($display->kind())->toBe(CanvasKind::EMBEDDED_DISPLAY)
        ->and(fn () => Canvas::of(panelCanvas(16, 16, oledSpec())))->toThrow(CanvasException::class, 'cannot draw into');
});

it('measures in the space the hook draws in', function () {
    [$stage] = gpuStageCanvas();
    [$display] = displayCanvas();
    $cpu_stage = Canvas::of(new FakeCPUStagedWindow('emu', panelCanvas(16, 16, oledSpec()), 64, 64, 1.0, StageFit::INTEGER_SCALE));
    $view = Canvas::of((new FakeWindow('main'))->gpu('scene', 'metal', 0, 0, 64, 48));

    expect([$stage->width(), $stage->height()])->toBe([64, 48])
        ->and($cpu_stage->size())->toBe([16, 16])
        ->and($view->size())->toBe([64, 48])
        ->and($display->size())->toBe([16, 16]);
});

it('draws on demand: nothing until present() asks for a frame', function () {
    [$canvas, $stage, $executor] = gpuStageCanvas();
    $canvas->draw(fn (Drawing2D $g) => $g->fillRect(0.0, 0.0, 10.0, 10.0, Color::hex('#fff')));

    expect($stage->renderFrame())->toBeFalse();

    $canvas->present();
    expect($stage->renderFrame())->toBeTrue()
        ->and($executor->draws)->toHaveCount(1)
        ->and($stage->renderFrame())->toBeFalse();

    $canvas->present();
    $stage->renderFrame();
    expect($executor->draws)->toHaveCount(2);
});

it('animate() runs the hook every frame until it is turned off', function () {
    [$canvas, $stage] = gpuStageCanvas();
    $seen = [];
    $canvas->draw(function (Drawing2D $g, Frame $frame) use (&$seen): void {
        $seen[] = $frame->index;
    })->animate();

    $stage->renderFrame();
    $stage->renderFrame();

    expect($seen)->toBe([0, 1]);

    $canvas->animate(false);
    expect($stage->renderFrame())->toBeFalse();
});

it('draw() alone asks for no frames, and replaces rather than stacks', function () {
    [$canvas, $stage] = gpuStageCanvas();
    $ran = [];

    $canvas->draw(function () use (&$ran): void {
        $ran[] = 'first';
    });

    expect($stage->renderFrame())->toBeFalse();

    $canvas->draw(function () use (&$ran): void {
        $ran[] = 'second';
    })->present();
    $stage->renderFrame();

    expect($ran)->toBe(['second']);
});

it('inks only what the hook drew this frame on a target that keeps its pixels', function () {
    [$canvas, $display, $panel] = displayCanvas();
    $white = Color::hex('#fff');

    $canvas->draw(fn (Drawing2D $g) => $g->fillRect(0.0, 0.0, 2.0, 2.0, $white))->present();
    $display->renderFrame();
    $canvas->draw(fn (Drawing2D $g) => $g->fillRect(12.0, 9.0, 2.0, 2.0, $white))->present();
    $display->renderFrame();

    expect($panel->transmits)->toBe([
        [0, 0, [3, 3, ...array_fill(0, 30, 0)], 16, 16],
        [0, 8, [...array_fill(0, 12, 0), 6, 6, 0, 0], 16, 8],
    ]);
});

it('the same sketch animates on a panel without erasing the whole surface', function () {
    [$canvas, $display, $panel] = displayCanvas();
    $white = Color::hex('#fff');
    $x = 0.0;

    $canvas->draw(function (Drawing2D $g) use (&$x, $white): void {
        $g->fillRect($x, 0.0, 1.0, 1.0, $white);
    })->animate();

    $display->renderFrame();
    $x = 1.0;
    $display->renderFrame();

    // Two one-pixel writes, not two whole frames: the dirty engine still sees
    // real damage because nothing cleared the surface between them.
    expect($panel->transmits)->toBe([
        [0, 0, [1, ...array_fill(0, 31, 0)], 16, 16],
        [0, 0, [1, 1, ...array_fill(0, 14, 0)], 16, 8],
    ]);
});

it('gives the hook the output own drawer, with no recording in between', function () {
    [$canvas, $stage] = gpuStageCanvas();
    $seen = null;
    $canvas->draw(function (Drawing2D $g) use (&$seen): void {
        $seen = $g;
    })->present();
    $stage->renderFrame();

    expect($seen)->toBe($stage->drawing())
        ->and($canvas->drawing())->toBe($stage->drawing())
        ->and($canvas->output())->toBe($stage);
});

it('sets the background the output clears to', function () {
    [$canvas, $stage, $executor] = gpuStageCanvas();
    $blue = Color::hex('#00f');

    $canvas->draw(fn () => null)->background($blue)->present();
    $stage->renderFrame();

    expect(end($executor->clears))->toEqual($blue);
});

it('shows, hides and closes through whatever it wraps', function () {
    [$stage_canvas, $stage] = gpuStageCanvas();
    [$display_canvas, $display, $panel] = displayCanvas();
    $view = (new FakeWindow('main'))->gpu('scene', 'metal', 0, 0, 64, 48);
    $view_canvas = Canvas::of($view);

    $view_canvas->hide()->show();
    $display_canvas->hide()->show();

    expect($stage_canvas->isVisible())->toBeTrue()
        ->and(fn () => $stage_canvas->hide())->toThrow(CanvasException::class, "A 'gpu-stage' canvas cannot hide().")
        ->and($view->applied_visible)->toBe([false, true])
        ->and($panel->switches)->toBe([false, true]);

    $stage_canvas->close();
    $view_canvas->close();
    $display_canvas->close();

    expect([$stage->isOpen(), $view->destroyed, $display->isOpen()])->toBe([false, true, false])
        ->and([$stage_canvas->isOpen(), $view_canvas->isOpen(), $display_canvas->isOpen()])->toBe([false, false, false])
        ->and(fn () => $stage_canvas->present())->toThrow(CanvasException::class, 'closed')
        ->and(fn () => $view_canvas->draw(fn () => null))->toThrow(CanvasException::class, 'closed');
});
