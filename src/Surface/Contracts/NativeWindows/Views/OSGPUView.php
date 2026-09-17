<?php

namespace Surface\Contracts\NativeWindows\Views;

use Surface\Contracts\Drawing\GPUDrawTarget;

/** A GPU region inside a window: a View that is also a DrawTarget. */
interface OSGPUView extends OSView, GPUDrawTarget
{
    /** Backing scale the twin last read from its window. */
    public function scale(): float;
}
