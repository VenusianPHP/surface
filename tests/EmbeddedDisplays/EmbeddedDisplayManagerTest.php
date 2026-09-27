<?php

use Surface\Contracts\Drawing\Color;
use Surface\Contracts\Drawing\CPUEngine;
use Surface\Contracts\Drawing\CPUHost;
use Surface\Contracts\Drawing\Drawing2D;
use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplayException;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\ChannelPalette;
use Surface\Contracts\Framebuffers\ChannelSpec;
use Surface\Contracts\Framebuffers\EInkColor;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\PixelFormat;
use Venusian\Surface\Tests\Support\Fakes\FakeDisplayPanel;
use Venusian\Surface\Tests\Support\Fakes\FakeEPaperPanel;
use Venusian\Surface\Tests\Support\Fakes\FakeStripPanel;

function paperSpec(): FormatSpec
{
    return new FormatSpec(PixelFormat::PLANAR, BitDepth::B1, palette: new ChannelPalette(new ChannelSpec(EInkColor::BLACK->value, true), new ChannelSpec(EInkColor::RED->value)));
}

it('attaches a canvas sized to the panel, in the panel format', function () {
    [$manager] = displayManager();

    $display = $manager->attach(new FakeDisplayPanel(16, 16, oledSpec()), 'oled');

    expect($display->name())->toBe('oled')
        ->and($display->drawableSize())->toBe([16, 16])
        ->and($display->hostFormat()->equals(oledSpec()))->toBeTrue()
        ->and($manager->display('oled'))->toBe($display)
        ->and($manager->has('oled'))->toBeTrue();
});

it('picks the engine from the panel kind when none is named', function () {
    [$manager] = displayManager();

    expect($manager->attach(new FakeDisplayPanel(16, 16, oledSpec()), 'oled')->engine())->toBe(CPUEngine::DIRTY)
        ->and($manager->attach(new FakeStripPanel(4, 2, new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B24)), 'strip')->engine())->toBe(CPUEngine::FULL)
        ->and($manager->attach(new FakeEPaperPanel(8, 1, paperSpec()), 'paper')->engine())->toBe(CPUEngine::EPAPER);
});

it('takes a named engine as a string or the enum, and the defaults from config', function () {
    [$manager] = displayManager(['defaults' => ['addressable' => 'full']]);

    expect($manager->attach(new FakeDisplayPanel(16, 16, oledSpec()), 'a')->engine())->toBe(CPUEngine::FULL)
        ->and($manager->attach(new FakeDisplayPanel(16, 16, oledSpec()), 'b', 'dirty')->engine())->toBe(CPUEngine::DIRTY)
        ->and($manager->attach(new FakeDisplayPanel(16, 16, oledSpec()), 'c', CPUEngine::PAGED)->engine())->toBe(CPUEngine::PAGED);
});

it('refuses a taken name, an unbooted panel, a host that is not the panel, and paged on a windowless panel', function () {
    [$manager] = displayManager();
    $manager->attach(new FakeDisplayPanel(16, 16, oledSpec()), 'oled');
    $cold = new FakeDisplayPanel(16, 16, oledSpec());
    $cold->booted = false;

    expect(fn () => $manager->attach(new FakeDisplayPanel(16, 16, oledSpec()), 'oled'))->toThrow(EmbeddedDisplayException::class, 'already attached')
        ->and(fn () => $manager->attach($cold, 'cold'))->toThrow(EmbeddedDisplayException::class, 'has not booted')
        ->and(fn () => $manager->attach(new FakeDisplayPanel(16, 16, oledSpec()), 'small', host: new CPUHost(8, 8, oledSpec())))->toThrow(EmbeddedDisplayException::class, '16x16')
        ->and(fn () => $manager->attach(new FakeStripPanel(8, 16, new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1)), 'strip', 'paged'))->toThrow(EmbeddedDisplayException::class, 'paged engine')
        ->and($manager->displays())->toHaveCount(1);
});

it('accepts a host of the right size and format, for the knobs only some engines read', function () {
    [$manager] = displayManager();
    $spec = new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1);

    $display = $manager->attach(new FakeDisplayPanel(8, 16, $spec), 'oled', 'paged', new CPUHost(8, 16, $spec, page_rows: 8));

    expect($display->engine())->toBe(CPUEngine::PAGED);
});

it('forgets a display when it is detached or closes itself', function () {
    [$manager] = displayManager();
    $a = new FakeDisplayPanel(16, 16, oledSpec());
    $b = new FakeDisplayPanel(16, 16, oledSpec());
    $manager->attach($a, 'a');
    $manager->attach($b, 'b')->close();

    $manager->detach('a');

    expect($manager->displays())->toBe([])
        ->and($a->switches)->toBe([false])
        ->and($b->switches)->toBe([false])
        ->and(fn () => $manager->detach('a'))->toThrow(EmbeddedDisplayException::class, 'No embedded display')
        ->and(fn () => $manager->display('nope'))->toThrow(EmbeddedDisplayException::class);
});

it('closes every display on destroy, rethrowing the first failure after the rest', function () {
    [$manager] = displayManager();
    $bad = new FakeDisplayPanel(16, 16, oledSpec());
    $good = new FakeDisplayPanel(16, 16, oledSpec());
    $manager->attach($bad, 'bad');
    $manager->attach($good, 'good');
    $bad->fail = new RuntimeException('bus gone');

    expect(fn () => $manager->destroy())->toThrow(RuntimeException::class, 'bus gone')
        ->and($good->switches)->toBe([false])
        ->and($manager->displays())->toBe([]);
});

it('hands each display the dock so a fault is mailed', function () {
    [$manager, $dock] = displayManager();
    $panel = new FakeDisplayPanel(16, 16, oledSpec());
    $display = $manager->attach($panel, 'oled')->onDraw(fn (Drawing2D $g) => $g->fillRect(0.0, 0.0, 1.0, 1.0, Color::hex('#fff')));
    $panel->fail = new RuntimeException('bus gone');

    $display->renderFrame();

    expect($dock->drain()->map(fn ($m) => $m->name)->all())->toBe(['display.faulted.oled']);
});
