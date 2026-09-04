<?php

namespace Surface\Contracts\NativeWindows\Events\View;

use Voyager\Contracts\IOPools\Occurrence;

/**
 * The user submitted a text field (Enter), named `<window>.<view>.submitted`.
 */
class TextSubmitted implements Occurrence
{
    public readonly string $name;

    public function __construct(
        string $view_name,
        string $window_name,
        public readonly string $value,
    ) {
        $this->name = "{$window_name}.{$view_name}.submitted";
    }
}
