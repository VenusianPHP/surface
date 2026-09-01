<?php

namespace Surface\Contracts\NativeWindows\Events;

/**
 * What kind of thing the engine observed. The name on the event narrows it;
 * the type says which family it belongs to, so a sketch can match broadly.
 */
enum SurfaceEventType: string
{
    case MENU = 'menu';
    case WINDOW_CLOSED = 'window.closed';
    case WINDOW_RESIZED = 'window.resized';
    case VIEW_CLICKED = 'view.clicked';
}
