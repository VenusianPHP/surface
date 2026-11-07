<?php

namespace Surface\Contracts\Drawing\Effects;

/** feBlend's modes. */
enum BlendMode: string
{
    case NORMAL = 'normal';
    case MULTIPLY = 'multiply';
    case SCREEN = 'screen';
    case DARKEN = 'darken';
    case LIGHTEN = 'lighten';
}
