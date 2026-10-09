<?php

namespace Surface\Contracts\Drawing;

/**
 * Whether a present waits for the display. Adaptive waits unless the frame is
 * late; Mailbox never waits and shows the newest frame at each refresh.
 */
enum VSync: string
{
    case Off = 'off';
    case On = 'on';
    case Adaptive = 'adaptive';
    case Mailbox = 'mailbox';

    /**
     * This mode, then what a device that lacks it presents with instead: On,
     * which every swapchain has.
     *
     * @return list<self>
     */
    public function fallbacks(): array
    {
        return $this === self::On ? [self::On] : [$this, self::On];
    }
}
