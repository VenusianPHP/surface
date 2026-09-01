<?php

use Surface\Contracts\NativeWindows\Events\SurfaceEvent;
use Surface\Contracts\NativeWindows\Events\SurfaceEventType;
use Surface\Core\ProgramShuttle;
use Voyager\IOPools\EventQueue;
use Surface\NativeWindows\Menus\MenuItemSpec;
use Venusian\Surface\Tests\Bridge\Fakes\FakeSession;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;
use Venusian\Surface\Tests\Support\Fakes\FakeWindowDriver;

/*
|--------------------------------------------------------------------------
| Event queue
|--------------------------------------------------------------------------
|
| Engines push during pump; the sketch drains after its tick. Drains hand
| back a Collection keyed by event name so a loop reads has()/get(), and
| leave the queue empty. Provisioning hands every window the shuttle's queue.
|
*/

function menuEvent(string $name, string $window = 'main'): SurfaceEvent
{
    return new SurfaceEvent(SurfaceEventType::MENU, $name, $window, ['id' => $name, 'label' => $name]);
}

it('drains pushed events keyed by name and answers has and get', function () {
    $queue = new EventQueue();
    $queue->push(menuEvent('do-thing'));
    $queue->push(menuEvent('other-thing'));

    $events = $queue->drain();

    expect($events)->toHaveCount(2)
        ->and($events->has('do-thing'))->toBeTrue()
        ->and($events->get('do-thing'))->toBeInstanceOf(SurfaceEvent::class)
        ->and($events->get('do-thing')->window)->toBe('main')
        ->and($events->has('nope'))->toBeFalse();
});

it('is empty after a drain', function () {
    $queue = new EventQueue();
    $queue->push(menuEvent('do-thing'));

    $queue->drain();

    expect($queue->drain())->toHaveCount(0);
});

it('drains empty when nothing was pushed', function () {
    expect((new EventQueue())->drain())->toHaveCount(0);
});

it('collapses same-name pushes within one tick to the last event', function () {
    $queue = new EventQueue();
    $queue->push(menuEvent('do-thing', 'main'));
    $queue->push(menuEvent('do-thing', 'inspector'));

    $events = $queue->drain();

    expect($events)->toHaveCount(1)
        ->and($events->get('do-thing')->window)->toBe('inspector');
});

// ── Windowable emit path ───────────────────────────────────────────────

function eventItem(): MenuItemSpec
{
    return MenuItemSpec::parseList([
        ['label' => 'Tools', 'items' => [
            ['id' => 'tools.do-thing', 'label' => 'Do Thing', 'event' => 'do-thing'],
        ]],
    ])[0]->items[0];
}

it('emits a MENU event through the sink with id and label payload', function () {
    $queue = new EventQueue();
    $window = new FakeWindow('main');
    $window->setEventSink($queue);

    $window->fireMenuItem(eventItem());

    $event = $queue->drain()->get('do-thing');
    expect($event->type)->toBe(SurfaceEventType::MENU)
        ->and($event->window)->toBe('main')
        ->and($event->payload)->toBe(['id' => 'tools.do-thing', 'label' => 'Do Thing']);
});

it('emits nothing without a sink instead of blowing up mid-pump', function () {
    $window = new FakeWindow('main');

    $window->fireMenuItem(eventItem());

    expect(true)->toBeTrue();
});

it('emits nothing for an item that has no event name', function () {
    $queue = new EventQueue();
    $window = new FakeWindow('main');
    $window->setEventSink($queue);

    $spec = MenuItemSpec::parseList([['label' => 'Quit', 'role' => 'quit']])[0];
    $window->fireMenuItem($spec);

    expect($queue->drain())->toHaveCount(0);
});

// ── Window closed ──────────────────────────────────────────────────────

it('emits WINDOW_CLOSED named per window so closes cannot collapse', function () {
    $queue = new EventQueue();
    $main = new FakeWindow('main');
    $inspector = new FakeWindow('inspector');
    $main->setEventSink($queue);
    $inspector->setEventSink($queue);

    $main->fireClosed();
    $inspector->fireClosed();

    $events = $queue->drain();
    expect($events->has('window.closed.main'))->toBeTrue()
        ->and($events->has('window.closed.inspector'))->toBeTrue()
        ->and($events->get('window.closed.main')->type)->toBe(SurfaceEventType::WINDOW_CLOSED)
        ->and($events->get('window.closed.main')->window)->toBe('main');
});

it('emits no close event without a sink', function () {
    $window = new FakeWindow('main');

    $window->fireClosed();

    expect(true)->toBeTrue();
});

// ── Shuttle wiring ─────────────────────────────────────────────────────

it('hands every provisioned window the shuttle queue, and events drains it', function () {
    $program = new ProgramShuttle((new FakeSession())->connect(), new FakeWindowDriver());
    $program->provisionWindow('main', 400, 600);

    /** @var FakeWindow $window */
    $window = $program->getWindowService()->get('main');
    $window->fireMenuItem(eventItem());

    $events = $program->events();
    expect($events->has('do-thing'))->toBeTrue()
        ->and($program->events())->toHaveCount(0);
});
