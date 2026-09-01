<?php

namespace Surface\NativeWindows\Drivers;

use Surface\Contracts\NativeWindows\OSWindow;
use Surface\Contracts\NativeWindows\LinuxOSWindow;
use Surface\Contracts\NativeWindows\LinuxOSWindowDriver;
use Surface\Contracts\NativeWindows\WindowableException;

class GTKWindowDriver extends NativeWindowDriver implements LinuxOSWindowDriver
{
    public function __construct(
        public readonly array $config
    ) {
        parent::__construct();
    }

    public function add(OSWindow $window): static
    {
        if(!($window instanceof LinuxOSWindow)) {
            throw new WindowableException("Windowed instance must implement LinuxOSWindow");
        }
        $this->windows->put($window->name(), $window);
        return $this;
    }
}