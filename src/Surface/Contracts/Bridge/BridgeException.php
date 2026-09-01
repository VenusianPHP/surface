<?php

namespace Surface\Contracts\Bridge;

use Surface\Contracts\Core\SurfaceLevelException;

/**
 * Raised when the link between PHPland and an OS windowing engine cannot be made.
 *
 * Engine packages subclass this so a sketch can catch every bridge failure by one
 * type without Surface ever naming AppKit or GTK.
 */
class BridgeException extends SurfaceLevelException
{

}
