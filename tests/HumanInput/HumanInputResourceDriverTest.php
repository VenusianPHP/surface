<?php

use Surface\Contracts\HumanInput\Events\GamepadConnected;
use Surface\Contracts\HumanInput\Events\GamepadDisconnected;
use Surface\Contracts\HumanInput\GamepadButton;
use Surface\Contracts\HumanInput\InputEngine;
use Surface\HumanInput\Devices\GamePad;
use Surface\HumanInput\HumanInputResourceDriver;
use Venusian\Surface\Tests\Support\Fakes\FakeButtonPad;
use Venusian\Surface\Tests\Support\Fakes\FakeInputEngine;

it('polls every connected engine and every attached circuit, once per tick', function () {
    $sdl = new FakeInputEngine();
    $gtk = new FakeInputEngine(InputEngine::GTK);
    [$manager, $dock] = inputManager(['default' => 'sdl3'], ['input.sdl3' => $sdl, 'input.gtk' => $gtk]);
    $manager->engine();
    $manager->engine('gtk');
    $ic = new FakeButtonPad([GamepadButton::SOUTH]);
    $manager->attach($ic, 'p1');

    (new HumanInputResourceDriver($dock, $manager))->tick();

    expect([$sdl->polls, $gtk->polls, $ic->polls])->toBe([1, 1, 1]);
});

it('starts no engine on its own', function () {
    $sdl = new FakeInputEngine();
    [$manager, $dock] = inputManager(['default' => 'sdl3'], ['input.sdl3' => $sdl]);

    (new HumanInputResourceDriver($dock, $manager))->tick();

    expect($sdl->connects)->toBe(0);
});

it('mails a connect for each pad it first sees and a disconnect when one goes', function () {
    $sdl = new FakeInputEngine();
    [$manager, $dock] = inputManager(['default' => 'sdl3'], ['input.sdl3' => $sdl]);
    $manager->engine();
    $ic = new FakeButtonPad([GamepadButton::SOUTH]);
    $manager->attach($ic, 'p1');
    $resource = new HumanInputResourceDriver($dock, $manager);

    $resource->tick();
    $first = $dock->drain();

    $sdl->pads = ['sdl3-7' => new GamePad('sdl3-7', 'Retro', [GamepadButton::SOUTH])];
    $resource->tick();
    $second = $dock->drain();

    $ic->connected = false;
    $resource->tick();
    $third = $dock->drain();

    expect($first->map(fn ($m) => $m->name)->all())->toBe(['input.gamepad.connected.p1'])
        ->and($first->first())->toBeInstanceOf(GamepadConnected::class)
        ->and($second->map(fn ($m) => $m->name)->all())->toBe(['input.gamepad.connected.sdl3-7'])
        ->and($second->first()->device_name)->toBe('Retro')
        ->and($third->map(fn ($m) => $m->name)->all())->toBe(['input.gamepad.disconnected.p1'])
        ->and($third->first())->toBeInstanceOf(GamepadDisconnected::class);
});

it('mails nothing while the set of pads holds still', function () {
    [$manager, $dock] = inputManager();
    $manager->attach(new FakeButtonPad([GamepadButton::SOUTH]), 'p1');
    $resource = new HumanInputResourceDriver($dock, $manager);
    $resource->tick();
    $dock->drain();

    $resource->tick();

    expect($dock->drain())->toHaveCount(0);
});

it('survives a circuit whose poll throws, mailing its disconnect once', function () {
    [$manager, $dock] = inputManager();
    $ic = new FakeButtonPad([GamepadButton::SOUTH]);
    $input = $manager->attach($ic, 'p1');
    $resource = new HumanInputResourceDriver($dock, $manager);
    $resource->tick();
    $dock->drain();

    $ic->poll_failure = new RuntimeException('bus gone');
    $resource->tick();
    $failed = $dock->drain();
    $resource->tick();
    $after = $dock->drain();

    expect($failed->map(fn ($m) => $m->name)->all())->toBe(['input.gamepad.disconnected.p1'])
        ->and($after)->toHaveCount(0)
        ->and($input->faulted())->toBeTrue()
        ->and($manager->gamePads())->toBe([]);
});

it('mails a connect again once a faulted circuit is re-attached', function () {
    [$manager, $dock] = inputManager();
    $ic = new FakeButtonPad([GamepadButton::SOUTH]);
    $manager->attach($ic, 'p1');
    $resource = new HumanInputResourceDriver($dock, $manager);
    $ic->poll_failure = new RuntimeException('bus gone');
    $resource->tick();
    $resource->tick();
    $dock->drain();

    $ic->poll_failure = null;
    $manager->detach('p1');
    $manager->attach($ic, 'p1');
    $resource->tick();

    expect($dock->drain()->map(fn ($m) => $m->name)->all())->toBe(['input.gamepad.connected.p1']);
});
