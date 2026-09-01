<?php

namespace Surface\Core\MagicAliases;

use Surface\Contracts\Bridge\BridgedOSSession;
use Surface\Contracts\NativeWindows\OSWindowDriver;
use Surface\Core\ProgramShuttle;
use Voyager\MagicAliases\MagicAlias;

/**
 * @method static ProgramShuttle get()
 * @method static BridgedOSSession getBridgedSession()
 * @method static OSWindowDriver getWindowService()
 * @method static array|null getMenuBarProfile(string $profile)
 */
class Program extends MagicAlias
{
    protected static function getMagicAliasAccessor(): string
    {
        return 'os-program';
    }
}