<?php

declare(strict_types=1);

use Surface\Contracts\HumanInput\Events\GamepadConnected;
use Surface\Contracts\HumanInput\GamepadAxis;
use Surface\Contracts\HumanInput\GamepadButton;
use Surface\Contracts\HumanInput\HumanInputException;
use Surface\Contracts\HumanInput\Key;
use Surface\HumanInput\Devices\GameController;
use Surface\HumanInput\Devices\GamePad;
use Surface\HumanInput\InputFrame;
use Venusian\Surface\Tests\Fixtures\FakeSession;

it('starts an engine for each connected session and stops it when the session leaves', function (): void {
    $one = new FakeBridgeDriver((new FakeSession())->connect());
    $two = new FakeBridgeDriver(new FakeSession());
    $input = inputOver(['one' => $one, 'two' => $two]);
    $input->extend('one', fn (InputFrame $frame): FakeInputEngine => new FakeInputEngine($frame));
    $input->extend('two', fn (InputFrame $frame): FakeInputEngine => new FakeInputEngine($frame));

    $input->poll();

    expect(array_keys($input->engines()))->toBe(['one']);

    $two->fake->connect();
    $input->poll();
    $first = $input->engines()['one'];

    expect(array_keys($input->engines()))->toBe(['one', 'two']);

    $one->fake->disconnect();
    $input->poll();
    $input->poll();

    expect(array_keys($input->engines()))->toBe(['two'])
        ->and($first->connects)->toBe(1)
        ->and($first->disconnects)->toBe(1);
});

it('keeps an engine started by hand whatever the bridge does, and starts it once', function (): void {
    $input = inputOver([]);
    $input->extend('hand', fn (InputFrame $frame): FakeInputEngine => new FakeInputEngine($frame));

    $engine = $input->engine('hand');
    $input->poll();

    expect($input->engine('hand'))->toBe($engine)
        ->and($input->engines())->toBe(['hand' => $engine])
        ->and($engine->connects)->toBe(1);
});

it('refuses to start by hand an engine nobody registered', function (): void {
    expect(fn () => inputOver([])->engine('beos'))
        ->toThrow(HumanInputException::class, "No input engine is registered for the 'beos' toolkit: install the package that provides it.");
});

it('hands each engine creator the frame and nothing else', function (): void {
    $frame = inputFrame();
    $given = [];
    $input = inputOver(['kit' => new FakeBridgeDriver((new FakeSession())->connect())], frame: $frame);
    $input->extend('kit', function (...$args) use (&$given): FakeInputEngine {
        $given = $args;

        return new FakeInputEngine($args[0]);
    });

    $input->poll();

    expect($given)->toBe([$frame]);
});

it('waits for a read to report a connected session that has no engine', function (): void {
    $session = new FakeSession();
    $input = inputOver(['glfw' => new FakeBridgeDriver($session)]);
    $keyboard = $input->keyboard();
    $session->connect();

    $input->poll();

    expect($input->engines())->toBe([])
        ->and(fn () => $keyboard->isDown(Key::A))->toThrow(HumanInputException::class, "No input engine is registered for the 'glfw' toolkit")
        ->and(fn () => $keyboard->isDown(Key::A))->toThrow(HumanInputException::class, "'glfw'")
        ->and(fn () => $input->mouse()->x())->toThrow(HumanInputException::class, "'glfw'");
});

it('reads pads while a connected session has no engine', function (): void {
    $frame = inputFrame();
    $source = new FakePadSource();
    $source->controllers = ['gc-1' => new GameController($frame, 'gc-1', 'DualSense', [GamepadButton::SOUTH], [GamepadAxis::LEFT_X])];
    $input = inputOver(['glfw' => new FakeBridgeDriver((new FakeSession())->connect())], 'os', frame: $frame);
    $input->extendPads('os', fn (): FakePadSource => $source);

    $input->poll();

    expect(array_keys($input->gameControllers()))->toBe(['gc-1'])
        ->and($input->gameControllers()['gc-1']->isDown(GamepadButton::SOUTH))->toBeFalse();
});

it('starts the named pad source on the first poll, without any session', function (): void {
    $source = new FakePadSource();
    $input = inputOver([], 'os');
    $input->extendPads('os', fn (): FakePadSource => $source);

    expect($input->pads())->toBeNull();

    $input->poll();
    $input->poll();

    expect($input->pads())->toBe($source)
        ->and($source->connects)->toBe(1)
        ->and($source->polls)->toBe(2);
});

it('starts no pad source when none is named', function (): void {
    $made = 0;
    $input = inputOver([]);
    $input->extendPads('os', function () use (&$made): FakePadSource {
        $made++;

        return new FakePadSource();
    });

    $input->poll();

    expect($input->pads())->toBeNull()
        ->and($made)->toBe(0);
});

it('reports a named pad source nobody registered on pad reads only, naming what is registered', function (): void {
    $frame = inputFrame();
    $engine = new FakeInputEngine($frame);
    $input = inputOver([], 'evdev', frame: $frame);
    $input->extend('kit', fn (): FakeInputEngine => $engine);
    $input->extendPads('sdl3', fn (): FakePadSource => new FakePadSource());
    $input->extendPads('hid', fn (): FakePadSource => new FakePadSource());
    $input->engine('kit');
    $engine->see(new FakeKey(Key::A, true));

    $input->poll();

    expect(fn () => $input->gameControllers())
        ->toThrow(HumanInputException::class, "human-input.pads.linux names the pad source 'evdev', which no package registered (registered: sdl3, hid)")
        ->and(fn () => $input->gamePads())->toThrow(HumanInputException::class, "'evdev'")
        ->and($input->keyboard()->isDown(Key::A))->toBeTrue();
});

it('reports a pad source that failed to connect on pad reads, and retries it each poll', function (): void {
    $frame = inputFrame();
    $attempts = 0;
    $input = inputOver([], 'os', frame: $frame);
    $input->extendPads('os', function () use (&$attempts): FakePadSource {
        $source = new FakePadSource();
        $source->connect_failure = ++$attempts < 3 ? new RuntimeException("/dev/input/event4: not in the input group ({$attempts})") : null;

        return $source;
    });

    $input->poll();

    expect(fn () => $input->gamePads())->toThrow(RuntimeException::class, 'not in the input group (1)')
        ->and($input->keyboard()->isDown(Key::A))->toBeFalse()
        ->and($input->pads())->toBeNull();

    $input->poll();

    expect(fn () => $input->gamePads())->toThrow(RuntimeException::class, 'not in the input group (2)');

    $input->poll();

    expect($input->gamePads())->toBe([])
        ->and($input->pads()?->connected())->toBeTrue()
        ->and($attempts)->toBe(3);
});

it('merges pads: the pad source, then circuits, a circuit replacing a device under its name', function (): void {
    $frame = inputFrame();
    $source = new FakePadSource();
    $source->pads = ['sdl3-1' => new GamePad($frame, 'sdl3-1', 'NES', [GamepadButton::SOUTH])];
    $source->controllers = [
        'p1' => new GameController($frame, 'p1', 'Xbox', [GamepadButton::SOUTH], [GamepadAxis::LEFT_X]),
        'gc-9' => new GameController($frame, 'gc-9', 'DualSense', [GamepadButton::SOUTH], [GamepadAxis::LEFT_X]),
    ];
    $input = inputOver([], 'os', frame: $frame);
    $input->extendPads('os', fn (): FakePadSource => $source);
    $circuit = $input->attach(new FakeButtonPad([GamepadButton::SOUTH]), 'p1');

    $input->poll();

    expect(array_map(fn ($pad): string => $pad->name(), $input->gamePads()))->toBe(['sdl3-1' => 'NES', 'p1' => 'p1'])
        ->and(array_map(fn ($pad): string => $pad->name(), $input->gameControllers()))->toBe(['gc-9' => 'DualSense'])
        ->and($input->gamePads()['p1'])->toBe($circuit->device());
});

it('keeps edges across polls until a frame reads them', function (): void {
    $frame = inputFrame();
    $engine = new FakeInputEngine($frame);
    $input = inputOver([], frame: $frame);
    $input->extend('kit', fn (): FakeInputEngine => $engine);
    $input->engine('kit');

    $engine->see(new FakeKey(Key::SPACE, true));
    $engine->see(new FakeKey(Key::SPACE, false));
    $input->poll();
    $input->poll();
    $input->poll();

    expect($input->keyboard()->isPressed(Key::SPACE))->toBeTrue()
        ->and($input->keyboard()->wasReleased(Key::SPACE))->toBeTrue()
        ->and($engine->settles)->toBe(0);

    $input->poll();

    expect($input->keyboard()->isPressed(Key::SPACE))->toBeFalse()
        ->and($engine->settles)->toBe(1);
});

it('catches up on the first read of a frame, with what the toolkit dispatched since the poll', function (): void {
    $frame = inputFrame();
    $engine = new FakeInputEngine($frame);
    $input = inputOver([], frame: $frame);
    $input->extend('kit', fn (): FakeInputEngine => $engine);
    $input->engine('kit');
    $input->poll();

    $engine->see(new FakeKey(Key::W, true, 'w'));

    expect($input->keyboard()->isDown(Key::W))->toBeTrue()
        ->and($input->keyboard()->text())->toBe('w')
        ->and($engine->polls)->toBe(2);
});

it('drops edges no frame read within two frame lengths', function (): void {
    $frame = inputFrame(1);
    $engine = new FakeInputEngine($frame);
    $input = inputOver([], frame: $frame);
    $input->extend('kit', fn (): FakeInputEngine => $engine);
    $input->engine('kit');

    $engine->see(new FakeKey(Key::SPACE, true));
    $engine->see(new FakeKey(Key::SPACE, false));
    $input->poll();
    $input->poll();

    expect($input->keyboard()->isPressed(Key::SPACE))->toBeFalse()
        ->and($input->keyboard()->wasReleased(Key::SPACE))->toBeFalse();
});

it('polls circuits in the poll only, never on a read', function (): void {
    $ic = new FakeButtonPad([GamepadButton::SOUTH]);
    $input = inputOver([]);
    $input->attach($ic, 'p1');

    $input->poll();
    $input->keyboard()->isDown(Key::A);
    $input->gamePads()['p1']->isDown(GamepadButton::SOUTH);

    expect($ic->polls)->toBe(1);
});

it('mails each pad that comes and goes once, faulted circuits included', function (): void {
    $mail = new ArrayObject();
    $frame = inputFrame();
    $source = new FakePadSource();
    $input = inputOver([], 'os', $mail, $frame);
    $input->extendPads('os', fn (): FakePadSource => $source);
    $ic = new FakeButtonPad([GamepadButton::SOUTH]);
    $input->attach($ic, 'p1');

    $input->poll();
    $source->controllers = ['gc-1' => new GameController($frame, 'gc-1', 'DualSense', [GamepadButton::SOUTH], [GamepadAxis::LEFT_X])];
    $input->poll();
    $input->poll();
    $source->controllers = [];
    $ic->poll_failure = new RuntimeException('bus gone');
    $input->poll();
    $input->poll();

    expect(array_map(fn (object $m): string => $m->name(), $mail->getArrayCopy()))->toBe([
        'input.gamepad.connected.p1',
        'input.gamepad.connected.gc-1',
        'input.gamepad.disconnected.p1',
        'input.gamepad.disconnected.gc-1',
    ])->and($mail[1])->toEqual(new GamepadConnected('gc-1', 'DualSense'));
});

it('attaches circuits under names nobody holds, and detaches only what it attached', function (): void {
    $input = inputOver([]);
    $input->attach(new FakeButtonPad([GamepadButton::SOUTH]), 'p1');

    expect(fn () => $input->attach(new FakeButtonPad([]), 'p1'))->toThrow(HumanInputException::class, "Input circuit 'p1' is already attached.");

    $input->detach('p1');

    expect($input->circuits())->toBe([])
        ->and(fn () => $input->detach('p1'))->toThrow(HumanInputException::class, "No input circuit named 'p1'.");
});

it('destroys circuits, engines and then the pad source, rethrowing the first failure after the rest', function (): void {
    $log = new ArrayObject();
    $frame = inputFrame();
    $one = new FakeInputEngine($frame, log: $log, label: 'one');
    $two = new FakeInputEngine($frame, log: $log, label: 'two');
    $one->disconnect_failure = new RuntimeException('one failed');
    $source = new FakePadSource($log);
    $source->disconnect_failure = new RuntimeException('pads failed');
    $input = inputOver([], 'os', frame: $frame);
    $input->extend('one', fn (): FakeInputEngine => $one);
    $input->extend('two', fn (): FakeInputEngine => $two);
    $input->extendPads('os', fn (): FakePadSource => $source);
    $input->engine('one');
    $input->engine('two');
    $input->attach(new FakeButtonPad([GamepadButton::SOUTH]), 'p1');
    $input->poll();

    expect(fn () => $input->destroy())->toThrow(RuntimeException::class, 'one failed')
        ->and($log->getArrayCopy())->toBe(['disconnect one', 'disconnect two', 'disconnect pads'])
        ->and($input->engines())->toBe([])
        ->and($input->circuits())->toBe([])
        ->and($input->pads())->toBeNull();
});
