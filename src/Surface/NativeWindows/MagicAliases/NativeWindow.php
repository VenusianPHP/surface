<?php

namespace Surface\NativeWindows\MagicAliases;

use Voyager\MagicAliases\MagicAlias;

class NativeWindow extends MagicAlias
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getMagicAliasAccessor(): string
    {
        return 'native-window';
    }
}