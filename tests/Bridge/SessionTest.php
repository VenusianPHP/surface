<?php

declare(strict_types=1);

use Surface\Bridge\BridgedToolkitSession;
use Surface\Bridge\ToolkitPump;
use Surface\Contracts\Bridge\BridgeException;
use Surface\Contracts\Windows\Mail\WindowClosed;
use Venusian\Surface\Tests\Fixtures\FakeSession;
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
