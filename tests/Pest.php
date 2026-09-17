<?php

use Surface\Contracts\Core\Events\SurfaceEvent;
use Surface\Core\IOPools\OSLevelResourceDriver;
use Surface\Core\LiveApplication;
use Surface\Contracts\Drawing\GPUEngine;
use Surface\Drawing\GPUEngineManager;
use Surface\HumanInput\HumanInputManager;
use Surface\Stage\StageManager;
use Venusian\Surface\Tests\Support\Fakes\FakeBindingVessel;
use Venusian\Surface\Tests\Support\Fakes\FakeConfigRepository;
use Venusian\Surface\Tests\Support\Fakes\FakeGPUEngineDriver;
use Venusian\Surface\Tests\Bridge\Fakes\FakeSession;
use Venusian\Surface\Tests\Support\Fakes\FakeVessel;
use Venusian\Surface\Tests\Support\Fakes\FakeWindowDriver;
use Voyager\IOPools\IOPoolDock;
use Voyager\NutsAndBolts\Collection;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Surface has no application container. Tests run against plain objects and
| the fakes in tests/Support/Fakes, so every shared policy — the session state
| machine, the window registry, the LiveApplication — is provable with no
| extension loaded and no engine package installed. The IOPoolDock is built
| bare (empty resources config, FakeVessel) and resources register directly.
|
| Engine sessions are proven on real hardware instead: macOS for AppKit, the
| Pi over `fnk` for GTK.
|
| tests/Views and the three orphaned tests/NativeWindows files are held out of
| the default suite by phpunit.xml. They reference the deleted
| Surface\NativeWindows\Views\* tree from before the 0.8 teardown.
|
*/

/** A bare dock: no vessel-resolved resources, everything registers directly. */
function bareDock(): IOPoolDock
{
    return new IOPoolDock(new FakeVessel(), ['resources' => []]);
}

/**
 * A LiveApplication wired the way the provider wires it: bare dock, fake
 * session, fake window driver, the OS-level resource registered as 'os'.
 *
 * @return array{LiveApplication, IOPoolDock, FakeSession, FakeWindowDriver}
 */
function liveApp(bool $connected = true): array
{
    $session = new FakeSession();

    if ($connected) {
        $session->connect();
    }

    $driver = new FakeWindowDriver();
    $dock = bareDock();
    $dock->resource('os', new OSLevelResourceDriver($dock, $session, $driver));

    return [new LiveApplication($dock, $session, $driver), $dock, $session, $driver];
}

/**
 * A StageManager over a flat-map vessel: metal and opengl fakes behind their
 * gpu aliases, a bare dock, and whatever stage host sessions the test binds.
 *
 * @return array{StageManager, IOPoolDock, FakeBindingVessel}
 */
function stageManager(array $stage_config = ['default' => 'sdl3'], array $bindings = []): array
{
    $dock = bareDock();
    $vessel = new FakeBindingVessel([
        'config' => new FakeConfigRepository([
            'stage' => $stage_config,
            'gpu' => ['default' => 'metal', 'engines' => ['metal' => ['alias' => 'gpu.metal'], 'opengl' => ['alias' => 'gpu.opengl']]],
        ]),
        'gpu.metal' => new FakeGPUEngineDriver(GPUEngine::METAL),
        'gpu.opengl' => new FakeGPUEngineDriver(GPUEngine::OPENGL),
        'io-pool' => $dock,
    ] + $bindings);
    $vessel->instance('gpu-engines', new GPUEngineManager($vessel));

    return [new StageManager($vessel), $dock, $vessel];
}

/**
 * A HumanInputManager over a flat-map vessel: the given human-input config, a
 * bare dock behind 'io-pool', and whatever input.<engine> fakes the test binds.
 *
 * @return array{HumanInputManager, IOPoolDock, FakeBindingVessel}
 */
function inputManager(array $config = ['default' => 'sdl3'], array $bindings = []): array
{
    $dock = bareDock();
    $vessel = new FakeBindingVessel([
        'config' => new FakeConfigRepository(['human-input' => $config]),
        'io-pool' => $dock,
    ] + $bindings);

    return [new HumanInputManager($vessel), $dock, $vessel];
}

/** The first piece of mail carrying this name, or null — the drained bag is a list, not an index. */
function mailNamed(Collection $bag, string $name): ?SurfaceEvent
{
    return $bag->first(fn (object $mail) => $mail instanceof SurfaceEvent && $mail->name === $name);
}
