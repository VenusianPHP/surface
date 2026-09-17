<?php

use Psr\Container\NotFoundExceptionInterface;
use Surface\Contracts\HumanInput\GamepadAxis;
use Surface\Contracts\HumanInput\GamepadButton;
use Surface\Contracts\HumanInput\HumanInputException;
use Surface\Contracts\HumanInput\InputEngine;
use Surface\HumanInput\Devices\GameController;
use Surface\HumanInput\Devices\GamePad;
use Surface\HumanInput\Devices\Keyboard;
use Venusian\Surface\Tests\Support\Fakes\FakeButtonPad;
use Venusian\Surface\Tests\Support\Fakes\FakeControllerPad;
use Venusian\Surface\Tests\Support\Fakes\FakeInputEngine;

it('defaults to sdl3 and resolves it through its configured alias, connecting once', function () {
    $sdl = new FakeInputEngine();
    [$manager] = inputManager(['default' => 'sdl3', 'engines' => ['sdl3' => ['alias' => 'input.sdl3']]], ['input.sdl3' => $sdl]);

    expect($manager->engine())->toBe($sdl)
        ->and($manager->engine('sdl3'))->toBe($sdl)
        ->and($sdl->connects)->toBe(1)
        ->and($manager->engines())->toBe(['sdl3' => $sdl]);
});

it('falls back to sdl3 with no config at all', function () {
    [$manager] = inputManager([], ['input.sdl3' => new FakeInputEngine()]);

    expect($manager->getDefaultDriver())->toBe('sdl3');
});

it('honours a rebound alias without code', function () {
    $other = new FakeInputEngine(InputEngine::GTK);
    [$manager] = inputManager(['default' => 'gtk', 'engines' => ['gtk' => ['alias' => 'input.my-gtk']]], ['input.my-gtk' => $other]);

    expect($manager->engine())->toBe($other);
});

it('surfaces a missing engine package as the container not-found', function () {
    [$manager] = inputManager(['default' => 'appkit']);

    try {
        $manager->engine();
        expect(false)->toBeTrue();
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(NotFoundExceptionInterface::class);
    }
});

it('extend() registers an engine Surface never heard of', function () {
    $custom = new FakeInputEngine();
    [$manager] = inputManager(['default' => 'sdl3']);
    $manager->extend('glfw', fn () => $custom);

    expect($manager->engine('glfw'))->toBe($custom);
});

it('reads keyboard and mouse from the default engine only', function () {
    $sdl = new FakeInputEngine();
    $sdl->keyboard = new Keyboard();
    $gtk = new FakeInputEngine(InputEngine::GTK);
    [$manager] = inputManager(['default' => 'sdl3'], ['input.sdl3' => $sdl, 'input.gtk' => $gtk]);
    $manager->engine('gtk');

    expect($manager->keyboard())->toBe($sdl->keyboard)
        ->and($manager->mouse())->toBeNull();
});

it('gathers pads and controllers from every connected engine and every attached circuit', function () {
    $sdl = new FakeInputEngine();
    $sdl->controllers = ['sdl3-1' => new GameController('sdl3-1', 'Xbox', [GamepadButton::SOUTH], [GamepadAxis::LEFT_X])];
    $gtk = new FakeInputEngine(InputEngine::GTK);
    $gtk->pads = ['event3' => new GamePad('event3', 'Retro', [GamepadButton::SOUTH])];
    [$manager] = inputManager(['default' => 'sdl3'], ['input.sdl3' => $sdl, 'input.gtk' => $gtk]);
    $manager->engine();
    $manager->engine('gtk');
    $manager->attach(new FakeButtonPad([GamepadButton::SOUTH]), 'p1');
    $manager->attach(new FakeControllerPad([GamepadButton::SOUTH], [GamepadAxis::LEFT_X, GamepadAxis::LEFT_Y]), 'stick');

    expect(array_keys($manager->gamePads()))->toBe(['event3', 'p1'])
        ->and(array_keys($manager->gameControllers()))->toBe(['sdl3-1', 'stick']);
});

it('reads circuits without ever starting an engine', function () {
    [$manager] = inputManager(['default' => 'sdl3']);
    $manager->attach(new FakeButtonPad([GamepadButton::SOUTH]), 'p1');

    expect(array_keys($manager->gamePads()))->toBe(['p1'])
        ->and($manager->engines())->toBe([]);
});

it('drops a disconnected circuit from the lists but keeps it attached', function () {
    [$manager] = inputManager();
    $ic = new FakeButtonPad([GamepadButton::SOUTH]);
    $manager->attach($ic, 'p1');
    $ic->connected = false;

    expect($manager->gamePads())->toBe([])
        ->and(array_keys($manager->circuits()))->toBe(['p1']);
});

it('refuses a taken circuit name and an unknown detach', function () {
    [$manager] = inputManager();
    $manager->attach(new FakeButtonPad([GamepadButton::SOUTH]), 'p1');

    expect(fn () => $manager->attach(new FakeButtonPad([GamepadButton::SOUTH]), 'p1'))->toThrow(HumanInputException::class, "Input circuit 'p1' is already attached.")
        ->and(fn () => $manager->detach('p2'))->toThrow(HumanInputException::class, "No input circuit named 'p2'.");

    $manager->detach('p1');
    expect($manager->circuits())->toBe([]);
});

it('disconnects every engine it connected on destroy, sparing none on failure', function () {
    $sdl = new FakeInputEngine();
    $gtk = new FakeInputEngine(InputEngine::GTK);
    $sdl->disconnect_failure = new RuntimeException('boom');
    [$manager] = inputManager(['default' => 'sdl3'], ['input.sdl3' => $sdl, 'input.gtk' => $gtk]);
    $manager->engine();
    $manager->engine('gtk');

    expect(fn () => $manager->destroy())->toThrow(RuntimeException::class, 'boom')
        ->and($gtk->disconnects)->toBe(1)
        ->and($manager->engines())->toBe([]);
});
