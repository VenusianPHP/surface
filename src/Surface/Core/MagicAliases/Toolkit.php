<?php

namespace Surface\Core\MagicAliases;

use Surface\Bridge\ToolkitManager;
use Surface\Windows\ToolkitWindowManager;

class Toolkit
{
    public static function bridge(): ToolkitManager
    {
        /** @var ToolkitManager */
        return app('toolkit-bridge');
    }

    public static function window(): ToolkitWindowManager
    {
        /** @var ToolkitWindowManager */
        return app('toolkit-windows');
    }
}