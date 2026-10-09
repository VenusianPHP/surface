<?php

namespace Surface\Contracts\Windows;

/**
 * What a staged window can do beyond what every stager does. A stager lists
 * the ones its backend has (its toolkit on this platform, display server
 * included); a call that needs one it lacks throws WindowException.
 */
enum WindowCapability: string
{
    /** move(), and moveToDisplay() while windowed. Wayland toplevels have no position. */
    case Position = 'position';

    /** setAlwaysOnTop() after open. */
    case AlwaysOnTop = 'always-on-top';

    /** setFocusable() after open. */
    case Focusable = 'focusable';

    case Opacity = 'opacity';

    case Icon = 'icon';

    /** keepAwake(): hold off the screen saver and display sleep. */
    case KeepAwake = 'keep-awake';

    case Attention = 'attention';

    /** setMode(WindowMode::Exclusive, $displayMode). */
    case ExclusiveFullscreen = 'exclusive-fullscreen';

    /** The stager posts WindowFrameDue from the display's refresh (a display link). */
    case FrameClock = 'frame-clock';

    /** hitTest(): drag and resize regions the app decides. */
    case HitTest = 'hit-test';
}
