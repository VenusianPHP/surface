<?php

namespace Surface\HumanInput\MagicAliases;

use Voyager\MagicAliases\MagicAlias;

/**
 * @method static \Surface\Contracts\HumanInput\InputEngineDriver engine(string|null $name = null)
 * @method static array engines()
 * @method static \Surface\HumanInput\ICInput attach(\Surface\Contracts\HumanInput\Circuits\ButtonPad $ic, string $name)
 * @method static void detach(string $name)
 * @method static array circuits()
 * @method static \Surface\Contracts\HumanInput\Devices\Keyboard|null keyboard()
 * @method static \Surface\Contracts\HumanInput\Devices\Mouse|null mouse()
 * @method static array gamePads()
 * @method static array gameControllers()
 * @method static void destroy()
 * @method static \Surface\HumanInput\HumanInputManager extend(string $driver, \Closure $callback)
 * @method static string getDefaultDriver()
 *
 * @see \Surface\HumanInput\HumanInputManager
 */
class HumanInput extends MagicAlias
{
    protected static function getMagicAliasAccessor(): string
    {
        return 'human-input';
    }
}
