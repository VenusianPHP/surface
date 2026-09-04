<?php

namespace Surface\Core\MagicAliases;

use Surface\Core\LiveApplication;
use Voyager\MagicAliases\MagicAlias;
use Surface\Contracts\Bridge\BridgedOSSession;
use Surface\Contracts\NativeWindows\OSWindowDriver;

/**
 * @method static LiveApplication get()
 * @method static BridgedOSSession getBridgedSession()
 * @method static OSWindowDriver getWindowService()
 * @method static array|null getMenuBarProfile(string $profile)
 */
class LiveApp extends MagicAlias
{
    protected static function getMagicAliasAccessor(): string
    {
        return 'live-app';
    }
}