<?php

namespace Surface\Contracts\Fonts;

/** Bitmap faces draw on the CPU; TrueType faces draw on every engine. */
enum FontType
{
    case Bitmap;
    case TrueType;
}
