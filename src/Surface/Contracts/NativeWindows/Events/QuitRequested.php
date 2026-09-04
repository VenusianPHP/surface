<?php

namespace Surface\Contracts\NativeWindows\Events;

use Voyager\Contracts\IOPools\Occurrence;

class QuitRequested implements Occurrence
{
    public function __construct(
        public readonly string $name = 'quit'
    ) {

    }
}
