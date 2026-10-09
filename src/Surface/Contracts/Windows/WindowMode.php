<?php

namespace Surface\Contracts\Windows;

/**
 * How a staged window occupies the screen. Fullscreen is borderless over the
 * display at its desktop mode; Exclusive switches the display to a DisplayMode
 * (a compositor that cannot switch modes, Wayland, scales the window to it).
 */
enum WindowMode: string
{
    case Windowed = 'windowed';
    case Maximized = 'maximized';
    case Minimized = 'minimized';
    case Fullscreen = 'fullscreen';
    case Exclusive = 'exclusive';
}
