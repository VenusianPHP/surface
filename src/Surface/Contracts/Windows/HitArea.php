<?php

namespace Surface\Contracts\Windows;

/** What a point in a staged window is, answered by its hitTest() callback. */
enum HitArea: string
{
    case Normal = 'normal';
    case Draggable = 'draggable';
    case ResizeTopLeft = 'resize-top-left';
    case ResizeTop = 'resize-top';
    case ResizeTopRight = 'resize-top-right';
    case ResizeRight = 'resize-right';
    case ResizeBottomRight = 'resize-bottom-right';
    case ResizeBottom = 'resize-bottom';
    case ResizeBottomLeft = 'resize-bottom-left';
    case ResizeLeft = 'resize-left';
}
