<?php

use GeneralPurposeIO\Contracts\IntegratedCircuits\RefreshMode;
use Surface\Contracts\Drawing\Color;
use Surface\Contracts\Drawing\CPUEngine;
use Surface\Contracts\Drawing\CPUHost;
use Surface\Contracts\Drawing\Drawing2D;
use Surface\Contracts\Drawing\Frame;
use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplayException;
use Surface\Contracts\EmbeddedDisplays\Events\DisplayFaulted;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\ChannelPalette;
use Surface\Contracts\Framebuffers\ChannelSpec;
use Surface\Contracts\Framebuffers\EInkColor;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Drawing\Canvases\EPaperCanvas;
use Surface\Drawing\Canvases\FullCanvas;
use Surface\Drawing\Canvases\PagedCanvas;
use Surface\EmbeddedDisplays\EmbeddedDisplay;
use Surface\Framebuffers\Php\PhpFramebufferDriver;
use Venusian\Surface\Tests\Support\Fakes\FakeDisplayPanel;
use Venusian\Surface\Tests\Support\Fakes\FakeEPaperPanel;
use Venusian\Surface\Tests\Support\Fakes\FakeStripPanel;

/** A 16x16 OLED on a real dirty canvas. @return array{EmbeddedDisplay, FakeDisplayPanel} */
function oledDisplay(?Closure $on_close = null): array
{
    $panel = new FakeDisplayPanel(16, 16, oledSpec());

    return [new EmbeddedDisplay('oled', $panel, panelCanvas(16, 16, oledSpec()), $on_close), $panel];
}

/** The whole 16x16 frame with a 2x2 square at the origin: page 0 columns 0 and 1 carry bits 0 and 1. */
function oledCornerFrame(): array
{
    return [3, 3, ...array_fill(0, 30, 0)];
}

it('sends the whole frame first, then only the damage, to a panel that addresses a window', function () {
    [$display, $panel] = oledDisplay();
    $white = Color::hex('#fff');
    $display->onDraw(fn (Drawing2D $g, Frame $f) => $f->index === 0
        ? $g->fillRect(0.0, 0.0, 2.0, 2.0, $white)
        : $g->fillRect(12.0, 9.0, 2.0, 2.0, $white));

    expect($display->renderFrame())->toBeTrue()
        ->and($display->renderFrame())->toBeTrue()
        ->and($panel->transmits)->toBe([
            [0, 0, oledCornerFrame(), 16, 16],
            [0, 8, [...array_fill(0, 12, 0), 6, 6, 0, 0], 16, 8],
        ]);
});

it('sends the whole frame every time to a panel that does not address a window', function () {
    $spec = new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B24);
    $panel = new FakeStripPanel(4, 2, $spec);
    $canvas = new FullCanvas(CPUEngine::FULL, (new PhpFramebufferDriver())->full($spec, 4, 2), new CPUHost(4, 2, $spec));
    $display = new EmbeddedDisplay('strip', $panel, $canvas);
    $display->onDraw(fn (Drawing2D $g) => $g->fillRect(0.0, 0.0, 1.0, 1.0, Color::hex('#f00')));

    $display->renderFrame();
    $display->renderFrame();

    $frame = [255, 0, 0, ...array_fill(0, 21, 0)];
    expect($panel->transmits)->toBe([[0, 0, $frame, 4, 2], [0, 0, $frame, 4, 2]]);
});

it('sends nothing when a frame changed nothing', function () {
    $spec = new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B24);
    $panel = new FakeStripPanel(4, 2, $spec);
    $display = new EmbeddedDisplay('strip', $panel, panelCanvas(4, 2, $spec));
    $display->onDraw(fn (Drawing2D $g, Frame $f) => $f->index === 0 ? $g->fillRect(0.0, 0.0, 1.0, 1.0, Color::hex('#f00')) : null);

    $display->renderFrame();
    $display->renderFrame();

    expect($panel->transmits)->toHaveCount(1);
});

it('refreshes a panel that shows on command, once per frame, after the write, in the mode asked for', function () {
    $spec = new FormatSpec(PixelFormat::PLANAR, BitDepth::B1, palette: new ChannelPalette(new ChannelSpec(EInkColor::BLACK->value, true), new ChannelSpec(EInkColor::RED->value)));
    $panel = new FakeEPaperPanel(8, 1, $spec);
    $canvas = new EPaperCanvas(CPUEngine::EPAPER, (new PhpFramebufferDriver())->epaper($spec, 8, 1), new CPUHost(8, 1, $spec));
    $display = new EmbeddedDisplay('paper', $panel, $canvas);
    $display->onDraw(fn (Drawing2D $g) => $g->fillRect(0.0, 0.0, 1.0, 1.0, Color::hex('#f00')));

    $display->renderFrame();
    $display->refreshMode(RefreshMode::PARTIAL)->renderFrame();

    expect($panel->transmits[0])->toBe([0, 0, [255, 128], 8, 1])
        ->and($panel->refreshes)->toBe([RefreshMode::FULL, RefreshMode::PARTIAL])
        ->and($panel->log)->toBe(['transmit', 'refresh', 'transmit', 'refresh']);
});

it('streams a paged canvas to the panel page by page, from inside the frame', function () {
    $spec = new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1);
    $panel = new FakeDisplayPanel(8, 16, $spec);
    $canvas = new PagedCanvas(CPUEngine::PAGED, (new PhpFramebufferDriver())->paged($spec, 8, 16, 8), new CPUHost(8, 16, $spec, page_rows: 8));
    $display = new EmbeddedDisplay('oled', $panel, $canvas);
    $display->onDraw(fn (Drawing2D $g) => $g->fillRect(0.0, 0.0, 8.0, 9.0, Color::hex('#fff')));

    $display->renderFrame();

    expect($panel->transmits)->toBe([
        [0, 0, array_fill(0, 8, 255), 8, 8],
        [0, 8, array_fill(0, 8, 1), 8, 8],
    ]);
});

it('refuses a paged canvas on a panel that cannot address a window', function () {
    $spec = new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1);
    $canvas = new PagedCanvas(CPUEngine::PAGED, (new PhpFramebufferDriver())->paged($spec, 8, 16, 8), new CPUHost(8, 16, $spec, page_rows: 8));

    expect(fn () => new EmbeddedDisplay('strip', new FakeStripPanel(8, 16, $spec), $canvas))
        ->toThrow(EmbeddedDisplayException::class, 'paged engine');
});

it('latches a fault when the panel throws: one piece of mail, nothing sent after', function () {
    [$display, $panel] = oledDisplay();
    $dock = bareDock();
    $display->setPool($dock)->onDraw(fn (Drawing2D $g) => $g->fillRect(0.0, 0.0, 2.0, 2.0, Color::hex('#fff')));
    $panel->fail = new RuntimeException('bus gone');

    expect($display->renderFrame())->toBeTrue()
        ->and($display->faulted())->toBeTrue()
        ->and($display->fault()->getMessage())->toBe('bus gone')
        ->and($display->renderFrame())->toBeFalse();

    $mail = $dock->drain();
    expect($mail->map(fn ($m) => $m->name)->all())->toBe(['display.faulted.oled'])
        ->and($mail->first())->toBeInstanceOf(DisplayFaulted::class)
        ->and($mail->first()->error)->toBe('bus gone')
        ->and($panel->transmits)->toBe([]);
});

it('lets a sketch hook exception propagate without faulting the display', function () {
    [$display] = oledDisplay();
    $display->onDraw(fn () => throw new LogicException('sketch bug'));

    expect(fn () => $display->renderFrame())->toThrow(LogicException::class, 'sketch bug')
        ->and($display->faulted())->toBeFalse();
});

it('skips frames while hidden and sends the whole frame again on show', function () {
    [$display, $panel] = oledDisplay();
    $display->onDraw(fn (Drawing2D $g) => $g->fillRect(0.0, 0.0, 2.0, 2.0, Color::hex('#fff')));
    $display->renderFrame();

    $display->hide();
    expect($display->renderFrame())->toBeFalse()->and($display->isVisible())->toBeFalse();

    $display->show();
    expect($panel->switches)->toBe([false, true])
        ->and($display->isVisible())->toBeTrue()
        ->and($panel->transmits)->toHaveCount(2)
        ->and($panel->transmits[1])->toBe([0, 0, oledCornerFrame(), 16, 16]);
});

it('says so when the panel cannot switch', function () {
    $spec = new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B24);
    $display = new EmbeddedDisplay('strip', new FakeStripPanel(4, 2, $spec), panelCanvas(4, 2, $spec));

    expect($display->switchable())->toBeFalse()
        ->and(fn () => $display->hide())->toThrow(EmbeddedDisplayException::class, 'cannot be switched');
});

it('closes once: output off, the owner told, no frames after', function () {
    $told = [];
    [$display, $panel] = oledDisplay(function (string $name) use (&$told): void {
        $told[] = $name;
    });

    $display->close();
    $display->close();

    expect($panel->switches)->toBe([false])
        ->and($told)->toBe(['oled'])
        ->and($display->isOpen())->toBeFalse()
        ->and($display->renderFrame())->toBeFalse()
        ->and(fn () => $display->show())->toThrow(EmbeddedDisplayException::class, 'is closed');
});

it('forwards the canvas: engine, format and size', function () {
    [$display] = oledDisplay();

    expect($display->name())->toBe('oled')
        ->and($display->engine())->toBe(CPUEngine::DIRTY)
        ->and($display->hostFormat()->equals(oledSpec()))->toBeTrue()
        ->and($display->drawableSize())->toBe([16, 16]);
});
