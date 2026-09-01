<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * An indeterminate busy indicator.
 *
 * Indeterminate only: the HttpPool cannot report mid-flight progress, so
 * Surface will not fake a determinate bar. A real progress view can exist
 * the day a pool learns to emit progress.
 */
interface OSSpinner extends OSView
{
    public function isSpinning(): bool;

    public function start(): static;

    public function stop(): static;
}
