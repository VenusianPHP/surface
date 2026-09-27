<?php

use Surface\Core\LiveApplication;
use Venusian\Surface\Tests\Bridge\Fakes\FakeSession;
use Venusian\Surface\Tests\Support\Fakes\FakeDisplayPanel;
use Venusian\Surface\Tests\Support\Fakes\FakeWindowDriver;

it('closes every embedded display on destroy', function () {
    [$manager, $dock] = displayManager();
    $panel = new FakeDisplayPanel(16, 16, oledSpec());
    $manager->attach($panel, 'oled');
    $session = new FakeSession();
    $session->connect();

    (new LiveApplication($dock, $session, new FakeWindowDriver(), null, null, $manager))->destroy();

    expect($panel->switches)->toBe([false])
        ->and($manager->displays())->toBe([]);
});
