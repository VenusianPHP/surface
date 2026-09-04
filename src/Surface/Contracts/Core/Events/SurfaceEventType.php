<?php

namespace Surface\Contracts\Core\Events;

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
    case BUTTON_CLICKED = 'button.clicked';
    case TEXT_CHANGED = 'text.changed';
    case TEXT_SUBMITTED = 'text.submitted';
    case VALUE_CHANGED = 'value.changed';
    case TOGGLED = 'toggled';
    case SELECTION_CHANGED = 'selection.changed';
    case QUIT = 'quit';
}
