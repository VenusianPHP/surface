<?php

namespace Surface\NativeWindows\Drivers;

use Surface\Contracts\NativeWindows\OSWindow;
use Surface\Contracts\NativeWindows\OSWindowDriver as DriverContract;
use Surface\Contracts\NativeWindows\WindowableException;
use Voyager\NutsAndBolts\Collection;

abstract class NativeWindowDriver implements DriverContract
{
    protected Collection $windows;

    public function __construct() {
        $this->windows = new Collection();
    }

    public function get(string $name): ?OSWindow
    {
        return $this->windows->get($name);
    }

    public function has(string $name): bool
    {
        return $this->windows->has($name);
    }

    public function all(): array
    {
        return $this->windows->values()->all();
    }

    public function destroyAll(): void
    {
        foreach ($this->windows as $window) {
            /** @var OSWindow $window */
            $window->destroy();
        }
        $this->windows = new Collection();
    }

    public function presentWindow(string $name): void
    {
        if(!$this->has($name)) {
            throw new WindowableException("Window $name does not exists");
        }

        $window = $this->get($name);
        if(!$window->isPresenting())
        {
            $window->present();
        }
    }

}