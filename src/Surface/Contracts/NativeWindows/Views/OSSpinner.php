<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * An indeterminate busy indicator.
 *
 * Indeterminate only: OS-level occurrences carry no mid-flight progress,
 * so Surface will not fake a determinate bar. A real progress view can ride
 * the http resource's Presumption::onProgress hook the day one is needed.
 */
interface OSSpinner extends OSView
{
    public function isSpinning(): bool;

    public function start(): static;

    public function stop(): static;
}
