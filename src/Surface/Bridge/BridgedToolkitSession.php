<?php

namespace Surface\Bridge;

use Surface\Contracts\Bridge\BridgeException;
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
     * Bring the session up, initializing the engine first if it has never run.
     * @return $this
     */
    public function connect(): static
    {
        if ($this->connected) return $this;

        $this->initialize();
        $this->connectToEngine();
        $this->connected = true;

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
     * Put this toolkit's native wait in charge of the loop's sleep and fold the loop's
     * waiter into it, so every wake of the loop ends the toolkit's sleep too.
     *
     * @param LoopInterface $loop
     * @return void
     * @throws BridgeException Unless connected, or when the waiter has no descriptor.
     */
    public function joinLoop(LoopInterface $loop): void
    {
        if (! $this->connected) {
            throw new BridgeException('Connect the session before joining a loop.');
        }

        $descriptor = $loop->descriptor();
        if ($descriptor === null) {
            throw new BridgeException('The loop waiter has no descriptor: install ext-kqueue (macOS) or ext-epoll (Linux) and set io-pools.pool_waiters.default accordingly.');
        }

        $this->wakeDescriptor($descriptor);
        $this->loop = $loop;
        $loop->resource(self::PUMP, new ToolkitPump($this));
        $loop->crown(self::PUMP);

        foreach ($this->outbox as $mail) {
            $loop->post($mail);
        }
        $this->outbox = [];
    }


    public function leaveLoop(): void
    {
        if (!is_null($this->loop))
        {
            $this->loop->forget(self::PUMP);
            $this->releaseWakeDescriptor();
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