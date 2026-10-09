<?php

namespace Surface\Bridge;

use Surface\Contracts\Bridge\ToolkitLibrary;
use Voyager\Contracts\Vessel\TheServiceContainer;

abstract class ToolkitBridgeDriver implements ToolkitLibrary
{
    protected ?BridgedToolkitSession $session = null;

    public function __construct(
        protected TheServiceContainer $app
    ) {}

    abstract public function connect(): BridgedToolkitSession;

    /**
     * The session connect() made, or null before the first connect(). Never makes one.
     *
     * @return BridgedToolkitSession|null
     */
    public function session(): ?BridgedToolkitSession
    {
        return $this->session;
    }
}