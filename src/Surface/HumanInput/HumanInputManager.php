<?php

namespace Surface\HumanInput;

use Closure;
use Surface\Bridge\ToolkitBridgeDriver;
use Surface\Bridge\ToolkitManager;
use Surface\Contracts\HumanInput\Circuits\ButtonPad;
use Surface\Contracts\HumanInput\Devices\GameController as GameControllerContract;
use Surface\Contracts\HumanInput\Devices\GamePad as GamePadContract;
use Surface\Contracts\HumanInput\Devices\Keyboard;
use Surface\Contracts\HumanInput\Devices\Mouse;
use Surface\Contracts\HumanInput\Events\GamepadConnected;
use Surface\Contracts\HumanInput\Events\GamepadDisconnected;
use Surface\Contracts\HumanInput\HumanInputException;
use Surface\Contracts\HumanInput\InputEngineDriver;
use Surface\Contracts\HumanInput\InputSource;
use Surface\Contracts\HumanInput\PadSource;
use Surface\HumanInput\Devices\MergedKeyboard;
use Surface\HumanInput\Devices\MergedMouse;
use Throwable;

/**
 * Keyboards, mice, game pads and game controllers from every source at once. Every
 * toolkit session the bridge has connected gets the input engine its package registered
 * with extend(); the one pad source config names, registered with extendPads(), starts
 * on the first poll with or without a session; circuits are attached by hand. poll()
 * applies them all and mails pads that came and went; edges stay until a frame reads
 * them (InputFrame). Surface names no toolkit and no package. What cannot answer is
 * reported by the reads that need it: a missing engine by keyboard and mouse reads, a
 * missing or failing pad source by pad reads, so neither stops the other.
 */
class HumanInputManager
{
    /** @var array<string, Closure(InputFrame): InputEngineDriver> by toolkit */
    protected array $engine_creators = [];

    /** @var array<string, Closure(InputFrame): PadSource> by name */
    protected array $pad_creators = [];

    /** @var array<string, InputEngineDriver> running, by toolkit, in start order */
    protected array $engines = [];

    /** @var array<string, true> toolkits whose engine was started by hand: kept whatever the bridge does */
    protected array $by_hand = [];

    /** @var list<string> toolkits with a connected session and no registered engine, as of the last poll */
    protected array $missing = [];

    protected ?PadSource $pads = null;

    protected bool $pads_missing = false;

    /** Why the named pad source's last connect failed; retried each poll. */
    protected ?Throwable $pads_failure = null;

    /** @var array<string, ICInput> */
    protected array $circuits = [];

    /** @var array<string, string> device id => name, as of the last poll */
    protected array $known = [];

    /** @var array{array<string, GamePadContract>, array<string, GameControllerContract>} the pad source's last pads and controllers that listed without throwing */
    protected array $source_listing = [[], []];

    protected readonly MergedKeyboard $keyboard;

    protected readonly MergedMouse $mouse;

    /**
     * @param string $os the human-input.pads key for this OS (device_os_family())
     * @param string|null $pad_source the pad source config names for this OS, or null for none
     * @param Closure(object): void $post hands mail to the loop
     */
    public function __construct(
        protected readonly ToolkitManager $toolkits,
        protected readonly InputFrame $frame,
        protected readonly string $os,
        protected readonly ?string $pad_source,
        protected readonly Closure $post,
    ) {
        $this->keyboard = new MergedKeyboard($frame, function (): array {
            $this->ensureEngines();

            return array_values(array_map(fn (InputEngineDriver $e): Keyboard => $e->keyboard(), $this->engines));
        });
        $this->mouse = new MergedMouse($frame, function (): array {
            $this->ensureEngines();

            return array_values(array_map(fn (InputEngineDriver $e): Mouse => $e->mouse(), $this->engines));
        });
        $frame->catchUpWith(fn () => $this->catchUp());
    }

    /**
     * Register the input engine for a toolkit's sessions, under the toolkit's bridge name.
     * The creator gets the frame its devices mark and closes over its own bridge driver.
     * Engines list no pads: the pad source does.
     *
     * @param string $toolkit
     * @param Closure(InputFrame): InputEngineDriver $creator
     * @return static
     */
    public function extend(string $toolkit, Closure $creator): static
    {
        $this->engine_creators[$toolkit] = $creator;

        return $this;
    }

    /**
     * Register a pad source under the name human-input.pads.<os> uses.
     *
     * @param string $name
     * @param Closure(InputFrame): PadSource $creator
     * @return static
     */
    public function extendPads(string $name, Closure $creator): static
    {
        $this->pad_creators[$name] = $creator;

        return $this;
    }

    /**
     * A toolkit's engine, started by hand if it is not running: for a session connected
     * outside the bridge, or a test. An engine started here runs until destroy().
     *
     * @param string $toolkit
     * @return InputEngineDriver
     * @throws HumanInputException No engine is registered for the toolkit.
     */
    public function engine(string $toolkit): InputEngineDriver
    {
        if (isset($this->engines[$toolkit])) {
            return $this->engines[$toolkit];
        }

        $engine = $this->create($toolkit)->connect();
        $this->by_hand[$toolkit] = true;

        return $this->engines[$toolkit] = $engine;
    }

    /** @return array<string, InputEngineDriver> running engines by toolkit, in start order */
    public function engines(): array
    {
        return $this->engines;
    }

    /** The pad source, once a poll connected it; null when none is named, none is registered under the name, or its connect failed. */
    public function pads(): ?PadSource
    {
        return $this->pads;
    }

    /**
     * Wrap a circuit as a device under $name. Polled with every poll; a name an engine or
     * pad source also uses is replaced by the circuit.
     *
     * @throws HumanInputException The name is taken by another circuit.
     */
    public function attach(ButtonPad $ic, string $name): ICInput
    {
        if (isset($this->circuits[$name])) {
            throw HumanInputException::nameTaken($name);
        }

        return $this->circuits[$name] = new ICInput($this->frame, $name, $ic);
    }

    /** @throws HumanInputException No circuit is attached under $name. */
    public function detach(string $name): void
    {
        if (! isset($this->circuits[$name])) {
            throw HumanInputException::noSuchCircuit($name);
        }

        unset($this->circuits[$name]);
    }

    /** @return array<string, ICInput> */
    public function circuits(): array
    {
        return $this->circuits;
    }

    /** Every running engine's keyboard as one. */
    public function keyboard(): Keyboard
    {
        $this->ensureEngines();

        return $this->keyboard;
    }

    /** Every running engine's mouse as one. */
    public function mouse(): Mouse
    {
        $this->ensureEngines();

        return $this->mouse;
    }

    /** @return array<string, GamePadContract> pads without sticks, by id */
    public function gamePads(): array
    {
        $this->ensurePads();

        return $this->collect(false);
    }

    /** @return array<string, GameControllerContract> pads with sticks, by id */
    public function gameControllers(): array
    {
        $this->ensurePads();

        return $this->collect(true);
    }

    /**
     * One poll: follow the bridge's sessions, connect the pad source (again, if its last
     * connect failed), clear the edges a
     * frame has read, apply engines, pad source and circuits, then mail pads that came
     * and went. Never waits beyond what an attached circuit's own read takes. A script
     * without the loop calls this once per frame.
     *
     * @return void
     */
    public function poll(): void
    {
        $this->follow();
        $this->startPads();

        $this->frame->applying(function (): void {
            if ($this->frame->spent()) {
                foreach ($this->sources() as $source) {
                    $source->settle();
                }

                foreach ($this->circuits as $circuit) {
                    $circuit->settle();
                }
            }

            foreach ($this->sources() as $source) {
                $source->poll();
            }

            foreach ($this->circuits as $circuit) {
                $circuit->poll();
            }
        });

        $this->announce();
    }

    /**
     * Program teardown: forget the circuits, disconnect every engine, then the pad
     * source. A failure does not spare the rest; the first is rethrown after them.
     *
     * @return void
     * @throws Throwable The first engine or pad source failure.
     */
    public function destroy(): void
    {
        $failure = null;
        $this->circuits = [];

        foreach ($this->engines as $engine) {
            try {
                $engine->disconnect();
            } catch (Throwable $e) {
                $failure ??= $e;
            }
        }

        if (! is_null($this->pads)) {
            try {
                $this->pads->disconnect();
            } catch (Throwable $e) {
                $failure ??= $e;
            }
        }

        [$this->engines, $this->by_hand, $this->missing, $this->known, $this->source_listing] = [[], [], [], [], [[], []]];
        $this->pads = null;
        $this->pads_missing = false;
        $this->pads_failure = null;

        if (! is_null($failure)) {
            throw $failure;
        }
    }

    /** Start an engine for every connected bridge session without one; stop those whose session left. */
    protected function follow(): void
    {
        $live = [];

        foreach ($this->toolkits->getDrivers() as $toolkit => $driver) {
            if ($driver instanceof ToolkitBridgeDriver && $driver->session()?->connected()) {
                $live[$toolkit] = true;
            }
        }

        $this->missing = [];

        foreach (array_keys($live) as $toolkit) {
            if (isset($this->engines[$toolkit])) {
                continue;
            }

            if (! isset($this->engine_creators[$toolkit])) {
                $this->missing[] = $toolkit;

                continue;
            }

            $this->engines[$toolkit] = $this->create($toolkit)->connect();
        }

        foreach ($this->engines as $toolkit => $engine) {
            if (! isset($live[$toolkit]) && ! isset($this->by_hand[$toolkit])) {
                $engine->disconnect();
                unset($this->engines[$toolkit]);
            }
        }
    }

    /** Connect the named pad source; a missing name or a failed connect waits for pad reads to report it, and the next poll tries again. */
    protected function startPads(): void
    {
        if (! is_null($this->pads) || is_null($this->pad_source)) {
            return;
        }

        $creator = $this->pad_creators[$this->pad_source] ?? null;
        $this->pads_missing = is_null($creator);

        if (is_null($creator)) {
            return;
        }

        try {
            $this->pads = $creator($this->frame)->connect();
            $this->pads_failure = null;
        } catch (Throwable $e) {
            $this->pads_failure = $e;
        }
    }

    /** @throws HumanInputException No engine is registered for the toolkit. */
    protected function create(string $toolkit): InputEngineDriver
    {
        $creator = $this->engine_creators[$toolkit] ?? throw HumanInputException::noEngine($toolkit);

        return $creator($this->frame);
    }

    /** @return list<InputSource> running engines in start order, then the pad source */
    protected function sources(): array
    {
        return [...array_values($this->engines), ...(is_null($this->pads) ? [] : [$this->pads])];
    }

    /** A frame's first read: apply what engines and pad source recorded since the poll, clearing nothing. Circuits wait for the poll; their reads sleep. */
    protected function catchUp(): void
    {
        foreach ($this->sources() as $source) {
            $source->poll();
        }
    }

    /** @throws HumanInputException A connected session has no engine. */
    protected function ensureEngines(): void
    {
        if ($this->missing !== []) {
            throw HumanInputException::noEngine($this->missing[0]);
        }
    }

    /**
     * @throws HumanInputException The named pad source is not registered.
     * @throws Throwable The named pad source's last connect failed, as it failed.
     */
    protected function ensurePads(): void
    {
        if ($this->pads_missing) {
            throw HumanInputException::noPadSource((string) $this->pad_source, $this->os, array_keys($this->pad_creators));
        }

        if (! is_null($this->pads_failure)) {
            throw $this->pads_failure;
        }
    }

    /**
     * The pad source's devices (or $source, a listing of them), then connected circuits, each
     * replacing any device under its name.
     *
     * @param array<string, GamePadContract>|null $source
     * @return array<string, GamePadContract>
     */
    protected function collect(bool $controllers, ?array $source = null): array
    {
        $devices = $source ?? (is_null($this->pads) ? [] : ($controllers ? $this->pads->gameControllers() : $this->pads->gamePads()));

        $circuits = array_filter($this->circuits, fn (ICInput $circuit): bool => $circuit->connected());
        $devices = array_diff_key($devices, $circuits);

        foreach ($circuits as $name => $circuit) {
            if (($circuit->device() instanceof GameControllerContract) === $controllers) {
                $devices[$name] = $circuit->device();
            }
        }

        return $devices;
    }

    /**
     * Mail the pads that came and went since the last poll. A pad source whose lists throw is
     * reported by pad reads, not here: poll() goes on, and its last listing stands until the
     * lists answer again, so a refusal mails no disconnect while circuits still mail theirs.
     */
    protected function announce(): void
    {
        try {
            $this->source_listing = is_null($this->pads) ? [[], []] : [$this->pads->gamePads(), $this->pads->gameControllers()];
        } catch (Throwable) {
            // reported by pad reads; the last listing stands
        }

        $listed = $this->collect(false, $this->source_listing[0]) + $this->collect(true, $this->source_listing[1]);

        $now = [];

        foreach ($listed as $id => $device) {
            $now[$id] = $device->name();
        }

        foreach (array_diff_key($now, $this->known) as $id => $name) {
            ($this->post)(new GamepadConnected((string) $id, $name));
        }

        foreach (array_diff_key($this->known, $now) as $id => $name) {
            ($this->post)(new GamepadDisconnected((string) $id));
        }

        $this->known = $now;
    }
}
