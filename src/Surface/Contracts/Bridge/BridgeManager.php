<?php

namespace Surface\Contracts\Bridge;

/**
 * Resolves the one session belonging to the OS this process is running on.
 *
 * Implementations own the singleton: a sketch is a long-running process, so the
 * same session is handed back for the life of it rather than a new bridge being
 * stood up per call.
 */
interface BridgeManager
{
    /**
     * Hand back the connected session for the host OS, standing it up if needed.
     * @return BridgedOSSession
     */
    public function connect(): BridgedOSSession;
}
