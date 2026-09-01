<?php

namespace Surface\Bridge;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Voyager\Contracts\Vessel\Vessel;
use Surface\Bridge\Actions\BuildMacOSSession;
use Surface\Bridge\Actions\BuildLinuxOSSession;
use Surface\Contracts\Bridge\BridgedOSSession;
use Surface\Contracts\Bridge\BridgeManager as ManagerContract;

/**
 * Picks the session for the host OS and keeps it for the life of the process.
 *
 * Surface never names an engine package. The build actions resolve a container
 * alias — 'mac.bridge' or 'linux.bridge' — that the installed jovian/venusian-*
 * package binds, so the alias string is the whole seam between the two layers.
 */
class BridgeManager implements ManagerContract
{
    /**
     * The session for this process, once one has been stood up
     * @var BridgedOSSession|null
     */
    protected ?BridgedOSSession $session = null;

    /**
     * @param Vessel $vessel Container the engine session is resolved out of.
     */
    public function __construct(
        protected Vessel $vessel
    ) {}

    /**
     * Hand back the connected session for the host OS, standing it up if needed.
     *
     * A session the caller disconnected is run back through its build action,
     * which reconnects the same container singleton rather than minting a second.
     *
     * @return BridgedOSSession
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface When the matching jovian/venusian-* package is not installed.
     */
    public function connect(): BridgedOSSession
    {
        if (is_null($this->session) || (!$this->session->connected())) {
            $this->session = device_os_family() == 'mac'
                ? BuildMacOSSession::run($this->vessel)
                : BuildLinuxOSSession::run($this->vessel);
        }


        return $this->session;
    }
}
