<?php

namespace Surface\Contracts\Windows\Menus;

/**
 * Items the toolkit acts on itself; none posts mail. About and Quit are the app's. The edit
 * roles act on the focused text (or list) with the platform's standard keys, so they take no
 * hotkey.
 */
enum MenuRole: string
{
    case ABOUT = 'about';
    case QUIT = 'quit';
    case UNDO = 'undo';
    case REDO = 'redo';
    case CUT = 'cut';
    case COPY = 'copy';
    case PASTE = 'paste';
    case SELECT_ALL = 'select-all';

    /** An Edit menu role: the toolkit runs it on the focused widget. */
    public function isEdit(): bool
    {
        return $this !== self::ABOUT && $this !== self::QUIT;
    }
}
