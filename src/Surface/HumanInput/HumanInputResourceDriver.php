<?php

namespace Surface\HumanInput;

use Surface\Contracts\HumanInput\Events\GamepadConnected;
use Surface\Contracts\HumanInput\Events\GamepadDisconnected;
use Voyager\Contracts\IOPools\IOResourceDriver;
use Voyager\Contracts\IOPools\PoolPump;

/**
 * The dock's input resource. One tick: poll every engine a sketch started,
 * poll every attached circuit, then mail whatever joined or left the set of
 * pads. Never waits on its own and never starts an engine — a sketch that
 * reads no input pays nothing. An attached I2C circuit's poll() does sleep
 * (wii: read_delay_us, 3 ms default; seesaw: about 1.25 ms per poll). A
 * circuit attached before it has booted reads disconnected and is never
 * polled: boot it first (boot_now: true). A circuit that throws is faulted
 * and mailed disconnected; detach() and attach() it to recover. Registered
 * after 'os' so native events pumped this tick are read this tick.
 */
final class HumanInputResourceDriver implements IOResourceDriver
{
    /** @var array<string, string> device id → device name, as of the last tick */
    private array $known = [];

    public function __construct(
        private readonly PoolPump $pool,
        private readonly HumanInputManager $manager,
    ) {}

    public function tick(): void
    {
        foreach ($this->manager->engines() as $engine) {
            $engine->poll();
        }

        foreach ($this->manager->circuits() as $input) {
            $input->poll();
        }

        $this->announce();
    }

    private function announce(): void
    {
        $now = [];

        foreach ($this->manager->gamePads() + $this->manager->gameControllers() as $id => $device) {
            $now[$id] = $device->name();
        }

        foreach (array_diff_key($now, $this->known) as $id => $name) {
            $this->pool->push(new GamepadConnected((string) $id, $name));
        }

        foreach (array_diff_key($this->known, $now) as $id => $name) {
            $this->pool->push(new GamepadDisconnected((string) $id));
        }

        $this->known = $now;
    }
}
