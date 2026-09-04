<?php

use Surface\Contracts\Core\Events\SurfaceEvent;
use Surface\Core\IOPools\OSLevelResourceDriver;
use Surface\Core\LiveApplication;
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

/** The first piece of mail carrying this name, or null — the drained bag is a list, not an index. */
function mailNamed(Collection $bag, string $name): ?SurfaceEvent
{
    return $bag->first(fn (object $mail) => $mail instanceof SurfaceEvent && $mail->name === $name);
}
