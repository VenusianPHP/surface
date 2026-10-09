<?php

namespace Surface\Bridge;

use Surface\Contracts\Bridge\BridgeException;
use Surface\Contracts\HumanInput\InputTap;
use WeakMap;
use Voyager\Contracts\IOPools\Loop as LoopInterface;
use Surface\Contracts\Bridge\BridgedToolkitSession as SessionBridge;

abstract class BridgedToolkitSession implements SessionBridge
{
    /**
     * Flag indicating the bridge is connected
     * @var bool
     */
    protected bool $connected = false;

    /**
     * Flag indicating initialization
     * @var bool
     */
    protected bool $initialized = false;

    /**
     * The loop this session pumps for, once joined.
     */
    protected ?LoopInterface $loop = null;

    /**
     * @var list<object> posted before a loop was joined
     */
    protected array $outbox = [];

    /**
     * @var array<string, object> pending mail by key; the last post under a key wins
     */
    protected array $latest = [];

    /**
     * @var array<int, InputTap> shown every native event, by object id
     */
    protected array $taps = [];

    /**
     * @var WeakMap<BridgedToolkitSession, true>|null every connected session in the process
     */
    private static ?WeakMap $live = null;

    /** Every session joins the loop under this prefix and its own object id, so sessions of several toolkits pump side by side. */
    public const string PUMP = 'bridge.toolkit';

    public function __construct() {
        $this->bootstrap();
    }

    /**
     * Block at most $budget_ns in the toolkit's own wait, dispatching what arrives. @return int events dispatched
     *
     * @param int $budget_ns
     * @return int
     */
    abstract public function pump(int $budget_ns): int;

    /**
     * Start the engine. Called at most once per process, never repeated or undone.
     * @return void
     */
    abstract protected function initializeEngine(): void;

    /**
     * Make the running engine present to the OS. Cyclable.
     * @return void
     */
    abstract protected function connectToEngine(): void;


    /**
     * Withdraw the running engine from the OS. Cyclable, and the inverse of connectToEngine().
     * @return void
     */
    abstract protected function disconnectEngine(): void;

    /**
     * Make $fd part of the toolkit's wait: readable ends the sleep.
     *
     * @param int $fd
     * @return void
     */
    abstract protected function wakeDescriptor(int $fd): void;

    /**
     * @return void
     */
    abstract protected function releaseWakeDescriptor(): void;

    /**
     * Whether the toolkit can fold the loop's descriptor into its own wait and
     * hold the sleep (AppKit, GTK, Qt). A toolkit that cannot (SDL 3, GLFW)
     * answers false and is polled at the pace instead.
     *
     * @return bool
     */
    protected function sleepsNatively(): bool
    {
        return true;
    }

    /**
     * Bring the session up, initializing the engine first if it has never run.
     * @return $this
     */
    public function connect(): static
    {
        if ($this->connected) return $this;

        $this->refuseSecondToolkit();
        $this->initialize();
        $this->connectToEngine();
        $this->connected = true;
        self::live()[$this] = true;

        return $this;
    }

    /**
     * Take the session down, draining the loop so the engine sees the last of our work.
     * @return void
     */
    public function disconnect(): void
    {
        if (! $this->connected) return;

        $this->disconnectEngine();
        $this->connected = false;
        unset(self::live()[$this]);
    }

    /**
     * On macOS every toolkit dequeues the application's one event queue in its own pump,
     * and GTK's and SDL's key translation runs only in theirs, so a second toolkit's
     * session loses key events. One toolkit at a time there: a session of another
     * class is refused while one is connected and both answer onMacOs(). Sessions of
     * the same class (one toolkit) are not limited, and Linux gives every toolkit its
     * own display connection.
     *
     * @return void
     * @throws BridgeException Another toolkit's session is connected on macOS.
     */
    protected function refuseSecondToolkit(): void
    {
        if (! $this->onMacOs()) {
            return;
        }

        foreach (self::live() as $session => $_) {
            if ($session::class !== static::class && $session->connected() && $session->onMacOs()) {
                $theirs = (new \ReflectionClass($session))->getShortName();
                $ours = (new \ReflectionClass($this))->getShortName();

                throw new BridgeException("{$theirs} is connected. On macOS one toolkit session pumps the application's events: disconnect it before connecting {$ours}.");
            }
        }
    }

    /**
     * Whether the one-toolkit rule applies here.
     *
     * @return bool
     */
    protected function onMacOs(): bool
    {
        return device_os_family() === 'mac';
    }

    /**
     * @return WeakMap<BridgedToolkitSession, true>
     */
    private static function live(): WeakMap
    {
        return self::$live ??= new WeakMap();
    }

    /**
     * Report whether the session is currently connected.
     * @return bool
     */
    public function connected(): bool
    {
        return $this->connected;
    }

    /**
     *  Hand mail from a native callback to the loop. Before joinLoop() it waits here;
     *  windows can be opened and pumped by hand before the sketch runs.
     *
     * @param object $mail
     * @return void
     */
    public function post(object $mail): void
    {
        if ($this->loop === null) {
            $this->outbox[] = $mail;
            return;
        }

        $this->loop->post($mail);
    }

    /**
     * Hold mail under $key until the pump flushes it, replacing whatever is pending there.
     *
     * @param string $key
     * @param object $mail
     * @return void
     */
    public function postLatest(string $key, object $mail): void
    {
        $this->latest[$key] = $mail;
    }

    /**
     * Deliver the pending latest mail: into the outbox before a loop, to the loop after.
     * ToolkitPump calls this after every pump, so a burst within one wait becomes one mail per key.
     *
     * @return void
     */
    public function flushLatest(): void
    {
        $latest = $this->latest;
        $this->latest = [];
        foreach ($latest as $mail) {
            $this->post($mail);
        }
    }

    /**
     * Drop the mail pending under $key, if any: its target is gone.
     *
     * @param string $key
     * @return void
     */
    public function forgetLatest(string $key): void
    {
        unset($this->latest[$key]);
    }

    /**
     * Show every native event this session handles to $tap, before the toolkit
     * dispatches it. Tapping twice is tapping once.
     *
     * @param InputTap $tap
     * @return void
     */
    public function tap(InputTap $tap): void
    {
        $this->taps[spl_object_id($tap)] = $tap;
    }

    /**
     * Stop showing native events to $tap. A tap never added is ignored.
     *
     * @param InputTap $tap
     * @return void
     */
    public function untap(InputTap $tap): void
    {
        unset($this->taps[spl_object_id($tap)]);
    }

    /**
     * Show a native event to every tap. The session's pump calls this for every event
     * it dequeues before dispatching it; where the toolkit dispatches internally, the
     * native hook the session installed (a GTK controller, a Qt event filter) calls it.
     *
     * @param object $native_event
     * @return void
     */
    public function see(object $native_event): void
    {
        foreach ($this->taps as $tap) {
            $tap->see($native_event);
        }
    }

    /**
     * The loop resource name this session pumps under.
     *
     * @return string
     */
    protected function pumpName(): string
    {
        return self::PUMP.'.'.spl_object_id($this);
    }

    /**
     * Put this toolkit's native wait in charge of the loop's sleep and fold the loop's
     * waiter into it, so every wake of the loop ends the toolkit's sleep too. A session
     * that does not sleep natively joins as a ToolkitPoller instead: ticked at the pace,
     * never crowned, no descriptor taken.
     *
     * @param LoopInterface $loop
     * @return void
     * @throws BridgeException Unless connected, or when a natively sleeping session's waiter has no descriptor.
     */
    public function joinLoop(LoopInterface $loop): void
    {
        if (! $this->connected) {
            throw new BridgeException('Connect the session before joining a loop.');
        }

        if ($this->sleepsNatively()) {
            $descriptor = $loop->descriptor();
            if ($descriptor === null) {
                throw new BridgeException('The loop waiter has no descriptor: install ext-kqueue (macOS) or ext-epoll (Linux) and set io-pools.pool_waiters.default accordingly.');
            }

            $this->wakeDescriptor($descriptor);
            $this->loop = $loop;
            $loop->resource($this->pumpName(), new ToolkitPump($this));
            $loop->crown($this->pumpName());
        } else {
            $this->loop = $loop;
            $loop->resource($this->pumpName(), new ToolkitPoller($this));
        }

        foreach ($this->outbox as $mail) {
            $loop->post($mail);
        }
        $this->outbox = [];
        $this->flushLatest();
    }


    public function leaveLoop(): void
    {
        if (!is_null($this->loop))
        {
            $this->loop->forget($this->pumpName());
            if ($this->sleepsNatively()) {
                $this->releaseWakeDescriptor();
            }
            $this->loop = null;
        }
    }

    /**
     * Start the engine on first use, and never again for the life of the process.
     * @return void
     */
    protected function initialize(): void
    {
        if (! $this->initialized) {
            $this->initializeEngine();
            $this->initialized = true;
        }
    }

    /**
     * Prepare the session at construction, leaving it initialised but not yet connected.
     * @return void
     */
    protected function bootstrap(): void
    {
        $this->initialize();
    }
}