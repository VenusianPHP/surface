<?php

namespace Surface\NativeWindows\Drivers;

use Surface\Contracts\NativeWindows\OSWindow;
use Surface\Contracts\NativeWindows\MacOSWindow;
use Surface\Contracts\NativeWindows\MacOSWindowDriver;
use Surface\Contracts\NativeWindows\WindowableException;

class AppKitWindowDriver extends NativeWindowDriver implements MacOSWindowDriver
{
    public function __construct(
        public readonly array $config
    ) {
        parent::__construct();
    }

    public function add(OSWindow $window): static
    {
        if(!($window instanceof MacOSWindow)) {
            throw new WindowableException("Windowed instance must implement MacOSWindow");
        }
        $this->windows->put($window->name(), $window);
        return $this;
    }
}