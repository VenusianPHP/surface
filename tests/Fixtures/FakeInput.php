<?php

declare(strict_types=1);

use Surface\Bridge\BridgedToolkitSession;
use Surface\Bridge\ToolkitBridgeDriver;
use Surface\Bridge\ToolkitManager;
use Surface\Contracts\HumanInput\Circuits\ButtonPad;
use Surface\Contracts\HumanInput\Circuits\GameController as ControllerCircuit;
use Surface\Contracts\HumanInput\GamepadAxis;
use Surface\Contracts\HumanInput\GamepadButton;
use Surface\Contracts\HumanInput\InputEngineDriver;
use Surface\Contracts\HumanInput\InputTap;
use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\MouseButton;
use Surface\Contracts\HumanInput\PadSource;
use Surface\HumanInput\Devices\GameController;
use Surface\HumanInput\Devices\GamePad;
use Surface\HumanInput\Devices\Keyboard;
use Surface\HumanInput\Devices\Mouse;
use Surface\HumanInput\HumanInputManager;
use Surface\HumanInput\InputFrame;
use Venusian\Surface\Tests\Fixtures\FakeSession;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\LoopWaiter;
use Voyager\IOPools\PromiseEngines\GuzzlePromiseEngine;
use Voyager\IOPools\ResourceRegistry;
use Voyager\IOPools\Waiter\StreamSelectWaiterBackend;

/** A bridge driver over a FakeSession, made without a container. */
final class FakeBridgeDriver extends ToolkitBridgeDriver
{
    public function __construct(public readonly FakeSession $fake)
    {
        $this->session = $fake;
    }

    public function connect(): FakeSession
    {
        return $this->fake->connect();
    }
}

/** A tap that keeps every native event it is shown. */
final class RecordingTap implements InputTap
{
    /** @var list<object> */
    public array $seen = [];

    public function see(object $native_event): void
    {
        $this->seen[] = $native_event;
    }
}

/** A pad source whose pads the test sets directly. */
final class FakePadSource implements PadSource
{
    public int $connects = 0;

    public int $disconnects = 0;

    public int $polls = 0;

    public int $settles = 0;

    public bool $connected = false;

    public ?Throwable $disconnect_failure = null;

    public ?Throwable $connect_failure = null;

    /** Thrown from gamePads() and gameControllers() while set: a source that refuses on read. */
    public ?Throwable $read_failure = null;

    /** @var array<string, GamePad> */
    public array $pads = [];

    /** @var array<string, GameController> */
    public array $controllers = [];

    public function __construct(public readonly ?ArrayObject $log = null) {}

    public function connect(): static
    {
        $this->connects++;

        if (! is_null($this->connect_failure)) {
            throw $this->connect_failure;
        }

        $this->connected = true;

        return $this;
    }

    public function disconnect(): void
    {
        $this->disconnects++;
        $this->connected = false;
        $this->log?->append('disconnect pads');

        if (! is_null($this->disconnect_failure)) {
            throw $this->disconnect_failure;
        }
    }

    public function connected(): bool
    {
        return $this->connected;
    }

    public function settle(): void
    {
        $this->settles++;

        foreach ([...$this->pads, ...$this->controllers] as $pad) {
            $pad->settle();
        }
    }

    public function poll(): void
    {
        $this->polls++;
    }

    /** @return array<string, GamePad> */
    public function gamePads(): array
    {
        if (! is_null($this->read_failure)) {
            throw $this->read_failure;
        }

        return $this->pads;
    }

    /** @return array<string, GameController> */
    public function gameControllers(): array
    {
        if (! is_null($this->read_failure)) {
            throw $this->read_failure;
        }

        return $this->controllers;
    }
}

/** A circuit whose held buttons and edges the test sets directly; a set failure is thrown from poll() / connected(). */
final class FakeButtonPad implements ButtonPad
{
    public int $polls = 0;

    public bool $connected = true;

    /** @var list<GamepadButton> */
    public array $down = [];

    /** @var list<GamepadButton> */
    public array $pressed = [];

    /** @var list<GamepadButton> */
    public array $released = [];

    public ?Throwable $poll_failure = null;

    public ?Throwable $connected_failure = null;

    /** @param list<GamepadButton> $supported */
    public function __construct(public array $supported) {}

    public function poll(): static
    {
        $this->polls++;

        if (! is_null($this->poll_failure)) {
            throw $this->poll_failure;
        }

        return $this;
    }

    public function connected(): bool
    {
        if (! is_null($this->connected_failure)) {
            throw $this->connected_failure;
        }

        return $this->connected;
    }

    public function supports(GamepadButton $button): bool
    {
        return in_array($button, $this->supported, true);
    }

    public function isDown(GamepadButton $button): bool
    {
        return in_array($button, $this->down, true);
    }

    public function isPressed(GamepadButton $button): bool
    {
        return in_array($button, $this->pressed, true);
    }

    public function wasReleased(GamepadButton $button): bool
    {
        return in_array($button, $this->released, true);
    }

    public function isHolding(GamepadButton $button, int $hold_ms): bool
    {
        return false;
    }
}

/** A circuit with axes; the test sets held buttons, edges and axis values directly. */
final class FakeControllerPad implements ControllerCircuit
{
    public int $polls = 0;

    public bool $connected = true;

    /** @var list<GamepadButton> */
    public array $down = [];

    /** @var list<GamepadButton> */
    public array $pressed = [];

    /** @var list<GamepadButton> */
    public array $released = [];

    public ?Throwable $poll_failure = null;

    /** @var array<string, float> keyed by GamepadAxis->value */
    public array $values = [];

    /**
     * @param list<GamepadButton> $supported
     * @param list<GamepadAxis> $axes
     */
    public function __construct(public array $supported, public array $axes) {}

    public function poll(): static
    {
        $this->polls++;

        if (! is_null($this->poll_failure)) {
            throw $this->poll_failure;
        }

        return $this;
    }

    public function connected(): bool
    {
        return $this->connected;
    }

    public function supports(GamepadButton $button): bool
    {
        return in_array($button, $this->supported, true);
    }

    public function isDown(GamepadButton $button): bool
    {
        return in_array($button, $this->down, true);
    }

    public function isPressed(GamepadButton $button): bool
    {
        return in_array($button, $this->pressed, true);
    }

    public function wasReleased(GamepadButton $button): bool
    {
        return in_array($button, $this->released, true);
    }

    public function isHolding(GamepadButton $button, int $hold_ms): bool
    {
        return false;
    }

    /** @return list<GamepadAxis> */
    public function supportedAxes(): array
    {
        return $this->axes;
    }

    public function axis(GamepadAxis $axis): float
    {
        return $this->values[$axis->value] ?? 0.0;
    }
}

/** A toolkit manager with $created drivers already made; extend() creators run without a container. $os is kept for the callers' readability. */
function bridgeOn(string $os, array $created = []): ToolkitManager
{
    return new class($os, $created) extends ToolkitManager {
        public function __construct(string $os, array $created)
        {
            $this->drivers = $created;
        }

        protected function createDriver(string $driver): mixed
        {
            return isset($this->customCreators[$driver])
                ? ($this->customCreators[$driver])()
                : throw new InvalidArgumentException("Driver [$driver] not supported.");
        }
    };
}

/** A frame an hour long unless given: within a test, only reads end it. */
function inputFrame(int $length_ns = 3_600_000_000_000): InputFrame
{
    return new InputFrame(fn (): int => $length_ns);
}

/** A manager over $drivers on Linux, the pad source $pads (none by default), its mail into $mail. */
function inputOver(array $drivers, ?string $pads = null, ?ArrayObject $mail = null, ?InputFrame $frame = null): HumanInputManager
{
    return new HumanInputManager(bridgeOn('linux', $drivers), $frame ?? inputFrame(), 'linux', $pads, function (object $sent) use ($mail): void {
        $mail?->append($sent);
    });
}

/** A loop over stream_select with a 5 ms pace, and its registry. @return array{EventLoop, ResourceRegistry} */
function inputLoop(): array
{
    $registry = new ResourceRegistry();

    return [new EventLoop($registry, new LoopWaiter($registry, new StreamSelectWaiterBackend(), 5_000_000), new GuzzlePromiseEngine()), $registry];
}

/** A key going down or up, as a toolkit hands it to a tap. */
final readonly class FakeKey
{
    public function __construct(public Key $key, public bool $down, public string $text = '') {}
}

/** The pointer moving to a point over a window. */
final readonly class FakeMove
{
    public function __construct(public float $x, public float $y, public ?string $window) {}
}

/** A mouse button going down or up. */
final readonly class FakeClick
{
    public function __construct(public MouseButton $button, public bool $down) {}
}

/** An input engine with no toolkit behind it: a tap that keeps what it is shown and applies it on poll. With a session, connect() taps it and disconnect() lets go. */
final class FakeInputEngine implements InputEngineDriver, InputTap
{
    public int $connects = 0;

    public int $disconnects = 0;

    public int $polls = 0;

    public int $settles = 0;

    public bool $connected = false;

    public ?Throwable $disconnect_failure = null;

    /** @var list<object> shown since the last poll */
    public array $seen = [];

    public readonly Keyboard $keyboard;

    public readonly Mouse $mouse;

    public function __construct(
        public readonly InputFrame $frame,
        public readonly ?BridgedToolkitSession $session = null,
        public readonly ?ArrayObject $log = null,
        public readonly string $label = 'engine',
    ) {
        $this->keyboard = new Keyboard($frame);
        $this->mouse = new Mouse($frame);
    }

    public function connect(): static
    {
        $this->connects++;
        $this->connected = true;
        $this->session?->tap($this);

        return $this;
    }

    public function disconnect(): void
    {
        $this->disconnects++;
        $this->connected = false;
        $this->session?->untap($this);
        $this->log?->append("disconnect {$this->label}");

        if (! is_null($this->disconnect_failure)) {
            throw $this->disconnect_failure;
        }
    }

    public function connected(): bool
    {
        return $this->connected;
    }

    public function see(object $native_event): void
    {
        $this->seen[] = $native_event;
    }

    public function settle(): void
    {
        $this->settles++;
        $this->keyboard->settle();
        $this->mouse->settle();
    }

    public function poll(): void
    {
        $this->polls++;
        [$seen, $this->seen] = [$this->seen, []];

        foreach ($seen as $event) {
            match (true) {
                $event instanceof FakeKey => $this->keyboard->update($event->key, $event->down)->appendText($event->text),
                $event instanceof FakeMove => $this->mouse->setPosition($event->x, $event->y, $event->window),
                $event instanceof FakeClick => $this->mouse->update($event->button, $event->down),
            };
        }
    }

    public function keyboard(): Keyboard
    {
        return $this->keyboard;
    }

    public function mouse(): Mouse
    {
        return $this->mouse;
    }
}
