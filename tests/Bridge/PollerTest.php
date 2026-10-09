<?php

declare(strict_types=1);

use Surface\Bridge\BridgedToolkitSession;
use Surface\Bridge\ToolkitPoller;
use Surface\Bridge\ToolkitPump;
use Surface\Contracts\Windows\Mail\WindowClosed;
use Surface\Contracts\Windows\Mail\WindowResized;
use Venusian\Surface\Tests\Fixtures\FakeSession;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\LoopWaiter;
use Voyager\IOPools\PromiseEngines\GuzzlePromiseEngine;
use Voyager\IOPools\ResourceRegistry;
use Voyager\IOPools\Waiter\EpollWaiterBackend;
use Voyager\IOPools\Waiter\KqueueWaiterBackend;
use Voyager\IOPools\Waiter\StreamSelectWaiterBackend;
use Voyager\Contracts\IOPools\WaiterBackendDriver;

/*
 * SDL 3 and GLFW fold no descriptor into their wait. A session that does not
 * sleep natively joins the loop as a pollable ticked at the pace, never crowned,
 * and needs no descriptor from the waiter.
 */

function pollingLoop(WaiterBackendDriver $backend): array
{
    $registry = new ResourceRegistry();

    return [new EventLoop($registry, new LoopWaiter($registry, $backend, 5_000_000), new GuzzlePromiseEngine()), $registry];
}

function descriptorBackend(): ?WaiterBackendDriver
{
    return match (true) {
        extension_loaded('kqueue') => new KqueueWaiterBackend(),
        extension_loaded('epoll') => new EpollWaiterBackend(),
        default => null,
    };
}

function pollingSession(): FakeSession
{
    $session = new FakeSession();
    $session->sleeps_natively = false;

    return $session->connect();
}

it('pumps without blocking on every tick and flushes the latest mail', function (): void {
    $session = new FakeSession();
    $poller = new ToolkitPoller($session);
    $session->postLatest('window.resized.a', $mail = new WindowResized('a', 1, 1));

    $poller->tick();
    $poller->tick();

    expect($session->pumped)->toBe([0, 0])
        ->and($session->outbox())->toBe([$mail]);
});

it('joins a loop with no descriptor as a pollable, uncrowned, and flushes held mail', function (): void {
    [$loop, $registry] = pollingLoop(new StreamSelectWaiterBackend());
    $session = pollingSession();
    $session->post($early = new WindowClosed('early'));

    $session->joinLoop($loop);
    $session->post($late = new WindowClosed('late'));

    expect($session->woken_by)->toBeNull()
        ->and($registry->sleeper())->toBeNull()
        ->and($registry->hasPollables())->toBeTrue()
        ->and($session->outbox())->toBe([])
        ->and($registry->mail())->toBe([$early, $late]);
});

it('ignores the descriptor and the crown even when the waiter has one', function (): void {
    [$loop, $registry] = pollingLoop(descriptorBackend());
    $session = pollingSession();

    $session->joinLoop($loop);

    expect($session->woken_by)->toBeNull()
        ->and($registry->sleeper())->toBeNull()
        ->and($registry->hasPollables())->toBeTrue();
})->skip(fn () => descriptorBackend() === null, 'needs ext-kqueue or ext-epoll');

it('is ticked by the registry at the pace', function (): void {
    [$loop, $registry] = pollingLoop(new StreamSelectWaiterBackend());
    $session = pollingSession();
    $session->joinLoop($loop);

    $registry->tick();
    $registry->tick();

    expect($session->pumped)->toBe([0, 0]);
});

it('leaves: forgets the poller without touching a descriptor it never took', function (): void {
    [$loop, $registry] = pollingLoop(new StreamSelectWaiterBackend());
    $session = pollingSession();
    $session->joinLoop($loop);

    $session->leaveLoop();
    $session->leaveLoop();

    expect($registry->hasPollables())->toBeFalse()
        ->and($session->released)->toBe(0);
});

it('still sleeps natively by default: the pump is crowned and the descriptor folded in', function (): void {
    [$loop, $registry] = pollingLoop(descriptorBackend());
    $session = (new FakeSession())->connect();

    $session->joinLoop($loop);

    expect($registry->sleeper())->toBeInstanceOf(ToolkitPump::class)
        ->and($session->woken_by)->not->toBeNull();
})->skip(fn () => descriptorBackend() === null, 'needs ext-kqueue or ext-epoll');
