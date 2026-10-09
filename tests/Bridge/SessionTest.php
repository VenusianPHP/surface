<?php

declare(strict_types=1);

use Surface\Bridge\BridgedToolkitSession;
use Surface\Bridge\ToolkitPump;
use Surface\Contracts\Bridge\BridgeException;
use Surface\Contracts\Windows\Mail\View\ViewResized;
use Surface\Contracts\Windows\Mail\WindowClosed;
use Surface\Contracts\Windows\Mail\WindowFocused;
use Surface\Contracts\Windows\Mail\WindowResized;
use Venusian\Surface\Tests\Fixtures\FakeSession;
use Venusian\Surface\Tests\Fixtures\OtherFakeSession;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\LoopWaiter;
use Voyager\IOPools\PromiseEngines\GuzzlePromiseEngine;
use Voyager\IOPools\ResourceRegistry;
use Voyager\IOPools\Waiter\EpollWaiterBackend;
use Voyager\IOPools\Waiter\KqueueWaiterBackend;
use Voyager\IOPools\Waiter\StreamSelectWaiterBackend;
use Voyager\Contracts\IOPools\WaiterBackendDriver;

function loopOver(WaiterBackendDriver $backend): array
{
    $registry = new ResourceRegistry();

    return [new EventLoop($registry, new LoopWaiter($registry, $backend, 5_000_000), new GuzzlePromiseEngine()), $registry];
}

function nestableBackend(): ?WaiterBackendDriver
{
    return match (true) {
        extension_loaded('kqueue') => new KqueueWaiterBackend(),
        extension_loaded('epoll') => new EpollWaiterBackend(),
        default => null,
    };
}

it('initialises at construction and connects once', function (): void {
    $session = new FakeSession();

    expect($session->calls)->toBe(['initialize'])
        ->and($session->connected())->toBeFalse();

    $session->connect()->connect();
    $session->disconnect();
    $session->disconnect();

    expect($session->calls)->toBe(['initialize', 'connect', 'disconnect'])
        ->and($session->connected())->toBeFalse();
});

it('holds mail posted before a loop is joined', function (): void {
    $session = new FakeSession();
    $session->post($mail = new WindowClosed('main'));

    expect($session->outbox())->toBe([$mail]);
});

it('refuses to join a loop before connecting', function (): void {
    [$loop] = loopOver(new StreamSelectWaiterBackend());

    expect(fn () => (new FakeSession())->joinLoop($loop))->toThrow(BridgeException::class, 'Connect the session');
});

it('refuses a loop whose waiter has no descriptor', function (): void {
    [$loop] = loopOver(new StreamSelectWaiterBackend());

    expect(fn () => (new FakeSession())->connect()->joinLoop($loop))->toThrow(BridgeException::class, 'no descriptor');
});

it('joins: folds the waiter descriptor in, holds the sleep, flushes held mail', function (): void {
    $backend = nestableBackend();
    [$loop, $registry] = loopOver($backend);
    $session = (new FakeSession())->connect();

    $session->post($early = new WindowClosed('early'));
    $session->joinLoop($loop);
    $session->post($late = new WindowClosed('late'));

    expect($session->woken_by)->toBe($backend->descriptor())
        ->and($session->outbox())->toBe([])
        ->and($registry->sleeper())->toBeInstanceOf(ToolkitPump::class)
        ->and($registry->mail())->toBe([$early, $late]);
})->skip(fn () => nestableBackend() === null, 'needs ext-kqueue or ext-epoll');

it('leaves: forgets the pump and releases the descriptor', function (): void {
    [$loop, $registry] = loopOver(nestableBackend());
    $session = (new FakeSession())->connect();
    $session->joinLoop($loop);

    $session->leaveLoop();
    $session->leaveLoop();

    expect($session->woken_by)->toBeNull()
        ->and($registry->sleeper())->toBeNull();
})->skip(fn () => nestableBackend() === null, 'needs ext-kqueue or ext-epoll');

it('pumps with the sleep budget, and without blocking on a tick', function (): void {
    $session = new FakeSession();
    $pump = new ToolkitPump($session);

    $pump->sleep(16_000_000);
    $pump->tick();

    expect($session->pumped)->toBe([16_000_000, 0])
        ->and(BridgedToolkitSession::PUMP)->toBe('bridge.toolkit');
});

it('keeps only the latest mail per key until the pump flushes it, in first-seen order', function (): void {
    $session = (new FakeSession())->connect();
    $session->postLatest('window.resized.main', new WindowResized('main', 10, 10));
    $session->postLatest('view.resized.main.a', new ViewResized('main', 'a', 'u', 1, 1));
    $session->postLatest('window.resized.main', new WindowResized('main', 20, 20));
    $session->post(new WindowFocused('main'));

    expect($session->outbox())->toEqual([new WindowFocused('main')]);

    (new ToolkitPump($session))->tick();

    expect($session->outbox())->toEqual([new WindowFocused('main'), new WindowResized('main', 20, 20), new ViewResized('main', 'a', 'u', 1, 1)]);

    (new ToolkitPump($session))->sleep(0);

    expect($session->outbox())->toHaveCount(3);
});

it('forgets a pending latest mail by key, and ignores a key with nothing pending', function (): void {
    $session = (new FakeSession())->connect();
    $session->postLatest('view.resized.main.a', new ViewResized('main', 'a', 'u', 1, 1));
    $session->postLatest('window.resized.main', $kept = new WindowResized('main', 2, 2));
    $session->forgetLatest('view.resized.main.a');
    $session->forgetLatest('view.resized.main.never');

    (new ToolkitPump($session))->tick();

    expect($session->outbox())->toBe([$kept]);
});

it('flushes the latest mail on sleep as well as tick', function (): void {
    $session = (new FakeSession())->connect();
    $session->postLatest('window.resized.main', new WindowResized('main', 5, 5));

    (new ToolkitPump($session))->sleep(1_000);

    expect($session->outbox())->toEqual([new WindowResized('main', 5, 5)]);
});

it('hands pending latest mail to the loop on join, after the held mail, and coalesces per pump once joined', function (): void {
    [$loop, $registry] = loopOver(nestableBackend());
    $session = (new FakeSession())->connect();
    $session->post($early = new WindowClosed('early'));
    $session->postLatest('window.resized.main', $first = new WindowResized('main', 1, 1));
    $session->joinLoop($loop);

    $session->postLatest('window.resized.main', new WindowResized('main', 2, 2));
    $session->postLatest('window.resized.main', $last = new WindowResized('main', 3, 3));

    expect($registry->mail())->toBe([$early, $first]);

    (new ToolkitPump($session))->tick();

    expect($registry->mail())->toBe([$last]);
})->skip(fn () => nestableBackend() === null, 'needs ext-kqueue or ext-epoll');

it('shows every native event to every tap until it is untapped', function (): void {
    $session = new FakeSession();
    $first = new RecordingTap();
    $second = new RecordingTap();
    $session->tap($first);
    $session->tap($second);
    $session->tap($second);

    $session->see($one = new stdClass());
    $session->untap($first);
    $session->see($two = new stdClass());

    expect($first->seen)->toBe([$one])
        ->and($second->seen)->toBe([$one, $two]);
});

it('joins two sessions to one loop side by side, each under its own name', function (): void {
    [$loop, $registry] = loopOver(new StreamSelectWaiterBackend());
    $gtk = new FakeSession();
    $gtk->sleeps_natively = false;
    $sdl = new FakeSession();
    $sdl->sleeps_natively = false;
    $gtk->connect()->joinLoop($loop);
    $sdl->connect()->joinLoop($loop);

    $registry->tick();
    $gtk->leaveLoop();
    $registry->tick();

    expect($gtk->pumped)->toBe([0])
        ->and($sdl->pumped)->toBe([0, 0]);
});

it('lets the last natively sleeping session hold the sleep and ticks the one before it', function (): void {
    [$loop, $registry] = loopOver(nestableBackend());
    $gtk = (new FakeSession())->connect();
    $qt = (new FakeSession())->connect();
    $gtk->joinLoop($loop);
    $qt->joinLoop($loop);

    $registry->tick();

    expect($gtk->pumped)->toBe([0])
        ->and($qt->pumped)->toBe([0])
        ->and($registry->sleeper())->toBeInstanceOf(ToolkitPump::class)
        ->and($registry->hasPollables())->toBeTrue();
})->skip(fn () => nestableBackend() === null, 'needs ext-kqueue or ext-epoll');

it('refuses a second toolkit session on macOS while one is connected, and allows it once that disconnects', function (): void {
    $appkit = new FakeSession();
    $sdl = new OtherFakeSession();
    $appkit->mac = $sdl->mac = true;
    $appkit->connect();

    expect(fn () => $sdl->connect())->toThrow(BridgeException::class, 'FakeSession is connected. On macOS one toolkit session pumps the application\'s events: disconnect it before connecting OtherFakeSession.')
        ->and($sdl->connected())->toBeFalse();

    $appkit->disconnect();
    $sdl->connect();

    expect($sdl->connected())->toBeTrue();
    $sdl->disconnect();
});

it('limits macOS to one toolkit, not one session, and Linux to neither', function (): void {
    $one = new FakeSession();
    $two = new FakeSession();
    $one->mac = $two->mac = true;
    $one->connect();
    $two->connect();

    expect($two->connected())->toBeTrue();
    $one->disconnect();
    $two->disconnect();

    $gtk = (new FakeSession())->connect();
    $sdl = (new OtherFakeSession())->connect();

    expect($sdl->connected())->toBeTrue();
    $gtk->disconnect();
    $sdl->disconnect();
});
