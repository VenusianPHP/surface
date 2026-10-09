<?php

namespace Surface\Contracts\Bridge;

use Surface\Contracts\HumanInput\InputTap;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Loop as LoopInterface;

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
     * Put this toolkit's native wait in charge of the loop's sleep and fold the loop's
     * waiter into it, so every wake of the loop ends the toolkit's sleep too. A session
     * that does not sleep natively joins as a ToolkitPoller instead: ticked at the pace,
     * never crowned, no descriptor taken.
     *
     * @param Loop $loop
     * @return void
     */
    public function joinLoop(Loop $loop): void;

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

    /**
     * Show every native event this session handles to $tap, before the toolkit
     * dispatches it. Tapping twice is tapping once.
     *
     * @param InputTap $tap
     * @return void
     */
    public function tap(InputTap $tap): void;

    /**
     * Stop showing native events to $tap. A tap never added is ignored.
     *
     * @param InputTap $tap
     * @return void
     */
    public function untap(InputTap $tap): void;
}
