<?php

namespace Surface\Bridge\Actions;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Surface\Contracts\Bridge\BridgedOSSession;
use Voyager\Contracts\Vessel\Vessel;

/**
 * Resolves and connects the Linux session bound by jovian/venusian-gtk.
 */
class BuildLinuxOSSession
{
    /**
     * Fetch the 'linux.bridge' session out of the container and connect it.
     *
     * The binding is a singleton, so calling this against a disconnected session
     * reconnects that same instance instead of standing up a second bridge.
     *
     * @param Vessel $vessel Container the session is resolved out of.
     * @return BridgedOSSession
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface When jovian/venusian-gtk is not installed.
     */
    public static function run(Vessel $vessel): BridgedOSSession
    {
        /** @var BridgedOSSession $bridge */
        $bridge = $vessel->get('linux.bridge');
        return $bridge->connect();
    }
}
