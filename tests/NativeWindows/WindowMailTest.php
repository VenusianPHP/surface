<?php

use Surface\Contracts\Core\Events\SurfaceEvent;
use Surface\Contracts\Core\Events\SurfaceEventType;
use Surface\NativeWindows\Menus\MenuItemSpec;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

/*
|--------------------------------------------------------------------------
| Window mail
|--------------------------------------------------------------------------
|
| Engines push SurfaceEvent occurrences into the dock during a pump; the
| sketch drains after its tick. The drained bag is an ordered list of typed
| mail — nothing coalesces, and two windows saying the same thing are two
| entries. Provisioning hands every window the application's dock.
|
*/

function eventItem(): MenuItemSpec
{
    return MenuItemSpec::parseList([
        ['label' => 'Tools', 'items' => [
            ['id' => 'tools.do-thing', 'label' => 'Do Thing', 'event' => 'do-thing'],
        ]],
    ])[0]->items[0];
}

it('emits MenuOccurrence mail with the item id, label, and event name', function () {
    $dock = bareDock();
    $window = new FakeWindow('main');
    $window->setPool($dock);

    $window->fireMenuItem(eventItem());

    $event = mailNamed($dock->drain(), 'menu.main');
    expect($event)->toBeInstanceOf(\Surface\Contracts\NativeWindows\Events\Menu\MenuOccurrence::class)
        ->and($event->type)->toBe(SurfaceEventType::MENU)
        ->and($event->window)->toBe('main')
        ->and($event->event_name)->toBe('do-thing')
        ->and($event->id)->toBe('tools.do-thing')
        ->and($event->label)->toBe('Do Thing');
});

it('emits nothing without a pool instead of blowing up mid-pump', function () {
    $window = new FakeWindow('main');

    $window->fireMenuItem(eventItem());

    expect(true)->toBeTrue();
});

it('emits nothing for an item that has no event name', function () {
    $dock = bareDock();
    $window = new FakeWindow('main');
    $window->setPool($dock);

    $spec = MenuItemSpec::parseList([['label' => 'Quit', 'role' => 'quit']])[0];
    $window->fireMenuItem($spec);

    expect($dock->drain())->toHaveCount(0);
});

it('same-name mail from one window stacks instead of collapsing', function () {
    $dock = bareDock();
    $window = new FakeWindow('main');
    $window->setPool($dock);

    $window->fireMenuItem(eventItem());
    $window->fireMenuItem(eventItem());

    expect($dock->drain()->filter(
        fn (SurfaceEvent $mail) => $mail->name === 'menu.main',
    ))->toHaveCount(2);
});

// ── Window closed ──────────────────────────────────────────────────────

it('emits WINDOW_CLOSED named per window so closes stay distinguishable', function () {
    $dock = bareDock();
    $main = new FakeWindow('main');
    $inspector = new FakeWindow('inspector');
    $main->setPool($dock);
    $inspector->setPool($dock);

    $main->fireClosed();
    $inspector->fireClosed();

    $bag = $dock->drain();
    expect($bag)->toHaveCount(2)
        ->and(mailNamed($bag, 'window.closed.main')->type)->toBe(SurfaceEventType::WINDOW_CLOSED)
        ->and(mailNamed($bag, 'window.closed.main')->window)->toBe('main')
        ->and(mailNamed($bag, 'window.closed.inspector'))->not->toBeNull();
});

it('emits no close event without a pool', function () {
    $window = new FakeWindow('main');

    $window->fireClosed();

    expect(true)->toBeTrue();
});

// ── Application wiring ─────────────────────────────────────────────────

it('hands every provisioned window the dock, and drain empties it', function () {
    [$app, $dock] = liveApp();
    $app->provisionWindow('main', 400, 600);

    /** @var FakeWindow $window */
    $window = $app->getWindowService()->get('main');
    $window->fireMenuItem(eventItem());

    expect(mailNamed($dock->drain(), 'menu.main'))->not->toBeNull()
        ->and($dock->drain())->toHaveCount(0);
});
