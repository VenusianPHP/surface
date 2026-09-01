<?php

namespace Surface\Bridge\Actions;

use Voyager\Contracts\Vessel\Vessel;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Surface\Contracts\Bridge\BridgedOSSession;

/**
 * Resolves and connects the macOS session bound by jovian/venusian-appkit.
 */
class BuildMacOSSession
{
    /**
     * Fetch the 'mac.bridge' session out of the container and connect it.
     *
     * The binding is a singleton, so calling this against a disconnected session
     * reconnects that same instance instead of standing up a second bridge.
     *
     * @param Vessel $vessel Container the session is resolved out of.
     * @return BridgedOSSession
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface When jovian/venusian-appkit is not installed.
     */
    public static function run(Vessel $vessel): BridgedOSSession
    {
        /** @var BridgedOSSession $bridge */
        $bridge = $vessel->get('mac.bridge');
        return $bridge->connect();
    }
}
