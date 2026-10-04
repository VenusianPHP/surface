<?php

use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\Endianness;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;
use Surface\Drawing\Velvet\VelvetGE;
use Surface\EmbeddedDisplays\EmbeddedDisplay;
use Surface\Fonts\ClassicFont;
use Surface\NutsAndBolts\Color;

it('sends a clock to a TFT whole once, then only the time it redraws', function () {
    $panel = new FakeWindowPanel(64, 32, new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B16, endianness: Endianness::MSB));
    $display = new EmbeddedDisplay('tft', $panel, framebuffers(), ['refreshing' => 'epaper', 'addressable' => 'dirty', 'whole' => 'full']);
    $engine = new VelvetGE($display->framebuffer(), rasterize()->driver());
    $face = new ClassicFont;
    $clock = fn (string $time) => function (RenderingEngine $g) use ($time, $face): void {
        $g->clear(Color::rgb(0, 0, 32));
        $g->strokeRect(1, 1, 62, 30, Color::rgb(255, 255, 255));
        $g->text($time, 8, 12, Color::rgb(255, 255, 0), $face);
    };

    foreach (['12:00', '12:01', '12:02'] as $time) {
        $engine->frame($clock($time));
        $display->present();
    }

    $windows = $panel->windows();
    expect($windows)->toHaveCount(3)
        ->and($windows[0])->toBe([0, 0, 64, 32]);
    // The time is one text call, 30 x 7 px at (8, 12): a frame that changes it sends that box, not the border or the background.
    foreach ([$windows[1], $windows[2]] as [$x, $y, $width, $height]) {
        expect($x)->toBeGreaterThanOrEqual(7)
            ->and($x + $width)->toBeLessThanOrEqual(39)
            ->and($y)->toBeGreaterThanOrEqual(11)
            ->and($y + $height)->toBeLessThanOrEqual(20);
    }
    expect($panel->calls[2][5])->toBe($display->boundFramebuffer()->flushRegion(new Region(...$windows[2]), $panel->formatSpec(), true));
});
