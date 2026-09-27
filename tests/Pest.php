<?php

use Surface\Contracts\Core\Events\SurfaceEvent;
use Surface\Core\IOPools\OSLevelResourceDriver;
use Surface\Core\LiveApplication;
use Surface\Contracts\Drawing\CPUEngine;
use Surface\Contracts\Drawing\CPUHost;
use Surface\Contracts\Drawing\GPUEngine;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\BitOrder;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\PageAxis;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\ScanDirection;
use Surface\Drawing\Canvases\DirtyCanvas;
use Surface\Drawing\CPUEngineManager;
use Surface\Drawing\Engines\DirtyEngine;
use Surface\Drawing\Engines\EPaperEngine;
use Surface\Drawing\Engines\FullEngine;
use Surface\Drawing\Engines\PagedEngine;
use Surface\Drawing\GPUEngineManager;
use Surface\EmbeddedDisplays\EmbeddedDisplayManager;
use Surface\Framebuffers\Php\PhpFramebufferDriver;
use Surface\Framebuffers\FramebufferManager;
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
            'cpu' => ['default' => 'dirty', 'engines' => ['dirty' => ['alias' => 'cpu.dirty'], 'full' => ['alias' => 'cpu.full']]],
            'framebuffers' => [],
        ]),
        'gpu.metal' => new FakeGPUEngineDriver(GPUEngine::METAL),
        'gpu.opengl' => new FakeGPUEngineDriver(GPUEngine::OPENGL),
        'io-pool' => $dock,
    ] + $bindings);
    $vessel->instance('gpu-engines', new GPUEngineManager($vessel));
    $framebuffers = new FramebufferManager($vessel);
    $vessel->instance(FramebufferManager::class, $framebuffers);
    $vessel->instance('cpu.dirty', new DirtyEngine($framebuffers));
    $vessel->instance('cpu.full', new FullEngine($framebuffers));
    $vessel->instance('cpu-engines', new CPUEngineManager($vessel));

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

/** The SSD1306's own format: one byte per column per 8-row page, bit 0 the top row. */
function oledSpec(): FormatSpec
{
    return new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1, ScanDirection::TOP_TO_BOTTOM, BitOrder::LSB_FIRST, page_axis: PageAxis::VERTICAL);
}

/** A real dirty canvas over the php framebuffer driver — no container, no fake. */
function panelCanvas(int $width, int $height, FormatSpec $spec): DirtyCanvas
{
    return new DirtyCanvas(CPUEngine::DIRTY, (new PhpFramebufferDriver())->dirty($spec, $width, $height), new CPUHost($width, $height, $spec));
}

/**
 * An EmbeddedDisplayManager over a flat-map vessel: the given embedded-displays
 * config, the four CPU engines a panel can default to behind their aliases
 * (real engines over the php framebuffer driver), and a bare dock.
 *
 * @return array{EmbeddedDisplayManager, IOPoolDock, FakeBindingVessel}
 */
function displayManager(array $config = [], array $bindings = []): array
{
    $dock = bareDock();
    $buffers = new PhpFramebufferDriver();
    $vessel = new FakeBindingVessel([
        'config' => new FakeConfigRepository([
            'embedded-displays' => $config,
            'cpu' => ['default' => 'dirty', 'engines' => [
                'dirty' => ['alias' => 'cpu.dirty'], 'full' => ['alias' => 'cpu.full'],
                'epaper' => ['alias' => 'cpu.epaper'], 'paged' => ['alias' => 'cpu.paged'],
            ]],
        ]),
        'cpu.dirty' => new DirtyEngine($buffers),
        'cpu.full' => new FullEngine($buffers),
        'cpu.epaper' => new EPaperEngine($buffers),
        'cpu.paged' => new PagedEngine($buffers),
        'io-pool' => $dock,
    ] + $bindings);
    $vessel->instance('cpu-engines', new CPUEngineManager($vessel));

    return [new EmbeddedDisplayManager($vessel), $dock, $vessel];
}
