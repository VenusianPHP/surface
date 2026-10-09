<?php

declare(strict_types=1);

use Surface\Contracts\HumanInput\Events\GamepadConnected;
use Surface\Contracts\HumanInput\GamepadButton;
use Surface\Contracts\HumanInput\Key;
use Surface\HumanInput\Devices\GamePad;
use Surface\HumanInput\HumanInputManager;
use Surface\HumanInput\HumanInputPoller;
use Surface\HumanInput\InputFrame;
use Venusian\Surface\Tests\Fixtures\FakeSession;

it('joins a loop as background input, and is destroyed when the loop stops', function (): void {
    [$loop, $registry] = inputLoop();
    $engine = null;
    $input = inputOver([]);
    $input->extend('hand', function (InputFrame $frame) use (&$engine): FakeInputEngine {
        return $engine = new FakeInputEngine($frame);
    });
    $input->engine('hand');

    HumanInputPoller::join($loop, $input);

    expect($registry->isBackground(HumanInputPoller::NAME))->toBeTrue()
        ->and($registry->hasPollables())->toBeTrue();

    $status = $loop->run();

    expect($status)->toBe(0)
        ->and($engine->disconnects)->toBe(1)
        ->and($input->engines())->toBe([]);
});

it('reads what a session pumped this turn, whichever registered first', function (): void {
    [$loop, $registry] = inputLoop();
    $session = new FakeSession();
    $session->sleeps_natively = false;
    $session->connect();
    $session->on_pump = fn (FakeSession $pumped) => $pumped->see(new FakeKey(Key::W, true, 'w'));
    $input = inputOver(['kit' => new FakeBridgeDriver($session)]);
    $input->extend('kit', fn (InputFrame $frame): FakeInputEngine => new FakeInputEngine($frame, $session));

    HumanInputPoller::join($loop, $input);
    $session->joinLoop($loop);
    $registry->tick();

    expect($input->keyboard()->isDown(Key::W))->toBeTrue()
        ->and($input->keyboard()->text())->toBe('w');
});

it('hands pad mail to the loop', function (): void {
    [$loop, $registry] = inputLoop();
    $frame = inputFrame();
    $source = new FakePadSource();
    $source->pads = ['evdev-event3' => new GamePad($frame, 'evdev-event3', 'DualSense', [GamepadButton::SOUTH])];
    $input = new HumanInputManager(bridgeOn('linux'), $frame, 'linux', 'os', fn (object $mail) => $loop->post($mail));
    $input->extendPads('os', fn (): FakePadSource => $source);
    HumanInputPoller::join($loop, $input);

    $registry->tick();

    expect($registry->mail())->toEqual([new GamepadConnected('evdev-event3', 'DualSense')]);
});
