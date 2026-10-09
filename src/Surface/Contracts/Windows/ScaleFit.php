<?php

namespace Surface\Contracts\Windows;

use Surface\Contracts\Framebuffers\Region;

/** Where a framebuffer of another size lands in the window. */
enum ScaleFit: string
{
    /** Over the whole window, aspect ignored. */
    case Stretch = 'stretch';

    /** The largest rect of the framebuffer's aspect, centred; bars around it. */
    case Letterbox = 'letterbox';

    /** The largest whole multiple of the framebuffer, centred; letterboxed down when it is larger than the window. */
    case Integer = 'integer';

    /**
     * Where a target of $width × $height lands in a surface of $across ×
     * $down pixels. An empty target fills the surface.
     */
    public function rect(int $across, int $down, int $width, int $height): Region
    {
        if ($this === self::Stretch || $width < 1 || $height < 1) {
            return new Region(0, 0, $across, $down);
        }
        $whole = min(intdiv($across, $width), intdiv($down, $height));
        if ($this === self::Integer && $whole >= 1) {
            [$w, $h] = [$width * $whole, $height * $whole];
        } else {
            $ratio = min($across / $width, $down / $height);
            [$w, $h] = [(int) round($width * $ratio), (int) round($height * $ratio)];
        }

        return new Region(intdiv($across - $w, 2), intdiv($down - $h, 2), $w, $h);
    }
}
