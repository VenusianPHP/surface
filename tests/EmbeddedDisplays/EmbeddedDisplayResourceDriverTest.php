<?php

use Surface\Contracts\Drawing\Color;
use Surface\Contracts\Drawing\Drawing2D;
use Surface\EmbeddedDisplays\EmbeddedDisplayResourceDriver;
use Venusian\Surface\Tests\Support\Fakes\FakeDisplayPanel;

it('runs one frame on every attached display per tick, and none when nothing is wanted', function () {
    [$manager] = displayManager();
    $a = new FakeDisplayPanel(16, 16, oledSpec());
    $b = new FakeDisplayPanel(16, 16, oledSpec());
    $ink = fn (Drawing2D $g) => $g->fillRect(0.0, 0.0, 1.0, 1.0, Color::hex('#fff'));
    $manager->attach($a, 'a')->onDraw($ink)->setContinuous(false);
    $manager->attach($b, 'b')->onDraw($ink)->setContinuous(false)->redraw();
    $resource = new EmbeddedDisplayResourceDriver($manager);

    $resource->tick();
    expect([count($a->transmits), count($b->transmits)])->toBe([0, 1]);

    $manager->display('a')->redraw();
    $resource->tick();
    expect([count($a->transmits), count($b->transmits)])->toBe([1, 1]);
});

it('does not tick a display that closed', function () {
    [$manager] = displayManager();
    $panel = new FakeDisplayPanel(16, 16, oledSpec());
    $manager->attach($panel, 'oled')->onDraw(fn (Drawing2D $g) => $g->fillRect(0.0, 0.0, 1.0, 1.0, Color::hex('#fff')))->close();

    (new EmbeddedDisplayResourceDriver($manager))->tick();

    expect($panel->transmits)->toBe([]);
});
