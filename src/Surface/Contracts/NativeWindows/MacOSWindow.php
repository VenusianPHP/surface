<?php

namespace Surface\Contracts\NativeWindows;

interface MacOSWindow extends OSWindow
{
    public function center(): static;
}