<?php

namespace Surface\Bridge\MagicAliases;

use Voyager\MagicAliases\MagicAlias;

/**
 * Static face of the bridge manager.
 *
 * @method static \Surface\Contracts\Bridge\BridgedOSSession connect()
 *
 * @see \Surface\Bridge\BridgeManager
 */
class OSAppBridge extends MagicAlias
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getMagicAliasAccessor(): string
    {
        return 'os-bridge';
    }
}
