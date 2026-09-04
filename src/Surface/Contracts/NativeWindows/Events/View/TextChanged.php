<?php

namespace Surface\Contracts\NativeWindows\Events\View;

use Voyager\Contracts\IOPools\Occurrence;

/**
 * A text view's content changed under the user's fingers, named
 * `<window>.<view>.changed`, carrying the new value read back from the
 * engine.
 */
class TextChanged implements Occurrence
{
    public readonly string $name;

    public function __construct(
        string $view_name,
        string $window_name,
        public readonly string $value,
    ) {
        $this->name = "{$window_name}.{$view_name}.changed";
    }
}
