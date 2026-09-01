<?php

use Venusian\Surface\Tests\Bridge\Fakes\FakeSession;

it('initialises the engine on construction and leaves the session disconnected', function () {
    $session = new FakeSession();

    expect($session->initializations)->toBe(1)
        ->and($session->engine_connections)->toBe(0)
        ->and($session->connected())->toBeFalse();
});

it('connects the engine without initialising it again', function () {
    $session = new FakeSession();

    $session->connect();

    expect($session->connected())->toBeTrue()
        ->and($session->engine_connections)->toBe(1)
        ->and($session->initializations)->toBe(1);
});

it('returns the session from connect so calls can chain', function () {
    $session = new FakeSession();

    expect($session->connect())->toBe($session);
});

it('ignores connect on an already connected session', function () {
    $session = new FakeSession();

    $session->connect();
    $session->connect();

    expect($session->engine_connections)->toBe(1)
        ->and($session->initializations)->toBe(1);
});

it('drains the loop with a zero budget before disconnecting the engine', function () {
    $session = new FakeSession();
    $session->connect();

    $session->disconnect();

    expect($session->pumps)->toBe([0])
        ->and($session->engine_disconnections)->toBe(1)
        ->and($session->connected())->toBeFalse();
});

it('ignores disconnect on a session that is not connected', function () {
    $session = new FakeSession();

    $session->disconnect();

    expect($session->engine_disconnections)->toBe(0)
        ->and($session->pumps)->toBe([]);
});

it('ignores a second disconnect', function () {
    $session = new FakeSession();
    $session->connect();

    $session->disconnect();
    $session->disconnect();

    expect($session->engine_disconnections)->toBe(1)
        ->and($session->pumps)->toBe([0]);
});

it('reconnects a disconnected session without initialising the engine twice', function () {
    $session = new FakeSession();

    $session->connect();
    $session->disconnect();
    $session->connect();

    expect($session->connected())->toBeTrue()
        ->and($session->initializations)->toBe(1)
        ->and($session->engine_connections)->toBe(2)
        ->and($session->engine_disconnections)->toBe(1);
});

it('pumps nothing and answers zero while disconnected', function () {
    $session = new FakeSession();

    expect($session->pump(50))->toBe(0)
        ->and($session->pumps)->toBe([]);
});

it('forwards the budget to the engine and answers its dispatch count while connected', function () {
    $session = new FakeSession();
    $session->connect();

    expect($session->pump(50))->toBe(7)
        ->and($session->pumps)->toBe([50]);
});

it('pumps with a zero budget by default', function () {
    $session = new FakeSession();
    $session->connect();

    $session->pump();

    expect($session->pumps)->toBe([0]);
});

it('stops pumping the engine once disconnected', function () {
    $session = new FakeSession();
    $session->connect();
    $session->pump(25);
    $session->disconnect();

    expect($session->pump(25))->toBe(0)
        ->and($session->pumps)->toBe([25, 0]);
});

it('hands the window request straight through to the engine', function () {
    $session = new FakeSession();
    $session->connect();

    $window = $session->provisionNewWindow('main', 400, 600);

    expect($session->provisions)->toBe([['name' => 'main', 'width' => 400, 'height' => 600]])
        ->and($window->name())->toBe('main');
});

it('provisions windows without a connection, because the abstract guards only the loop', function () {
    $session = new FakeSession();

    $session->provisionNewWindow('main', 400, 600);

    expect($session->connected())->toBeFalse()
        ->and($session->provisions)->toHaveCount(1);
});
