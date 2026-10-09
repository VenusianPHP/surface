<?php

namespace Surface\Contracts\Windows;

/** How a framebuffer of another size is sampled onto the window. */
enum ScaleFilter: string
{
    case Linear = 'linear';
    case Nearest = 'nearest';
}
