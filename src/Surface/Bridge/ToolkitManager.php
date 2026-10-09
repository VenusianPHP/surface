<?php

namespace Surface\Bridge;

use ReflectionException;
use Voyager\NutsAndBolts\Manager;

/**
 * The toolkit bridge: one driver per toolkit, registered by the toolkit's own
 * package through extend(). Surface names no toolkit; an unregistered name is
 * refused by the manager as any unknown driver.
 */
class ToolkitManager extends Manager
{
    /**
     * @throws ReflectionException
     */
    public function getDefaultDriver(): string
    {
        $os = device_os_family();

        return config("bridge.toolkit.{$os}.default", 'gtk');
    }
}
