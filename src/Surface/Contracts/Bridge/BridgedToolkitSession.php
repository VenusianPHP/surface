<?php

namespace Surface\Contracts\Bridge;

interface BridgedToolkitSession
{
    /**
     * Bring the session up, initialising the engine first if it has never run.
     * @return static
     */
    public function connect(): static;

    /**
     * Take the session down, draining anything the engine still has queued.
     * @return void
     */
    public function disconnect(): void;

    /**
     * Report whether the session is currently connected.
     * @return bool
     */
    public function connected(): bool;
    /**
     * Hold mail under $key until the next flush, replacing whatever is pending there.
     * Bursts (resizes) become one mail per key per pump.
     *
     * @param string $key e.g. `window.resized.<window>`, `view.resized.<window>.<path>`
     * @param object $mail
     * @return void
     */
    public function postLatest(string $key, object $mail): void;

    /**
     * Deliver the pending latest mail in first-seen key order.
     * @return void
     */
    public function flushLatest(): void;

    /**
     * Drop the mail pending under $key, if any: its target is gone (view unwatched or
     * removed, window closing).
     *
     * @param string $key
     * @return void
     */
    public function forgetLatest(string $key): void;
}