<?php

namespace Surface\Windows;

use Surface\Bridge\ToolkitManager;
use Surface\Contracts\Windows\Display;
use Surface\Contracts\Windows\StagedWindow;
use Surface\Contracts\Windows\StagedWindowDriver;
use Surface\Contracts\Windows\WindowException;

/**
 * app('staged-windows'): opens staged windows through a stager, the one the
 * 'toolkit' option names or config's bridge.stage.<os>.default, and remembers
 * which stager holds each name.
 */
class StagedWindowManager
{
    private const string NO_STAGER = "No stager configured: set bridge.stage.<os>.default, or pass ['toolkit' => …] to open().";

    /** @var array<string, string> window name → stager name */
    protected array $homes = [];

    public function __construct(
        protected readonly ToolkitManager $toolkits,
        protected readonly ?string $default,
    ) {}

    /**
     * @param  array<string, mixed>  $options  'toolkit' names the stager; the rest is what StagedWindow::options() takes.
     */
    public function open(string $name, int $width, int $height, array $options = []): StagedWindow
    {
        $toolkit = null;
        if (array_key_exists('toolkit', $options)) {
            $toolkit = $options['toolkit'];
            if (! is_string($toolkit)) {
                throw new WindowException("Staged window '{$name}' option 'toolkit' is a string, got ".get_debug_type($toolkit).'.');
            }
            unset($options['toolkit']);
        }
        $this->sweep();
        if (isset($this->homes[$name])) {
            throw new WindowException("A staged window named '{$name}' is already open (through the '{$this->homes[$name]}' stager).");
        }
        $stager = $toolkit ?? $this->default ?? throw new WindowException(self::NO_STAGER);

        $window = $this->driver($stager)->openStaged($name, $width, $height, $options);
        $this->homes[$name] = $stager;

        return $window;
    }

    public function get(string $name): ?StagedWindow
    {
        $this->sweep();

        return isset($this->homes[$name]) ? $this->driver($this->homes[$name])->getStaged($name) : null;
    }

    /** @return array<string, StagedWindow> by name, every stager used */
    public function all(): array
    {
        $this->sweep();
        $all = [];
        foreach ($this->homes as $name => $stager) {
            $all[$name] = $this->driver($stager)->getStaged($name);
        }

        return $all;
    }

    public function closeAll(): void
    {
        foreach (array_unique($this->homes) as $stager) {
            $this->driver($stager)->closeAllStaged();
        }
        $this->homes = [];
    }

    /** @return list<Display> every display the stager sees, primary first */
    public function displays(?string $toolkit = null): array
    {
        return $this->driver($toolkit)->displays();
    }

    public function primaryDisplay(?string $toolkit = null): Display
    {
        return $this->driver($toolkit)->primaryDisplay();
    }

    /** The stager named, or the configured default; never the toolkit default, which may stage nothing. */
    public function driver(?string $toolkit = null): StagedWindowDriver
    {
        $driver = $this->toolkits->driver($toolkit ?? $this->default ?? throw new WindowException(self::NO_STAGER));

        return $driver instanceof StagedWindowDriver
            ? $driver
            : throw new WindowException(get_debug_type($driver).' does not stage windows.');
    }

    /** Drop names whose stager no longer has them: closed from the window or natively. */
    protected function sweep(): void
    {
        foreach ($this->homes as $name => $stager) {
            if (! $this->driver($stager)->hasStaged($name)) {
                unset($this->homes[$name]);
            }
        }
    }
}
