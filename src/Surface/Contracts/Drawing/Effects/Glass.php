<?php

namespace Surface\Contracts\Drawing\Effects;

use Surface\Contracts\Drawing\DrawingException;
use Surface\NutsAndBolts\Color;

/**
 * A glass pane's look, for RenderingEngine::glass(): what is under the pane, blurred by $blur and seen through it.
 * Lengths are in the caller's units, scaled with the transform. Per pixel, from the rounded box's signed distance d
 * and outward normal n (every engine, the same steps; the drawing-path spec's G4):
 *
 *   f          (1 + d / band)² held to 0..1 near the rim, band = $bevel, else $refraction, else 1 pixel
 *   sample     the blurred backdrop at the pixel moved by -n · $refraction · f, mixed with the sharp one by 1 − $frost
 *   colour     saturated by $saturation about luma; $tint mixed in by its alpha; + $light (0..1 of white)
 *   rim        + $rim · its alpha · f · (½ + n · (−0.6, −0.8) / 2): an edge light from the top left
 *   grain      + $grain · 0.04 · (hash(x, y) − ½) of white, the hash an integer one of the pixel, the same every frame
 *   edge       anti-aliased by d
 *
 * $shadow is the blur of a drop shadow under the pane, in $shadowColor: painted after the backdrop is read and before the
 * pane, so the pane does not blur its own shadow and its rounded corners meet the shadow with nothing cut out.
 */
final readonly class Glass
{
    public function __construct(
        public float $blur = 16.0,
        public ?Color $tint = null,
        public float $saturation = 1.0,
        public float $light = 0.0,
        public ?Color $rim = null,
        public float $bevel = 0.0,
        public float $refraction = 0.0,
        public float $grain = 0.0,
        public float $frost = 1.0,
        public float $shadow = 0.0,
        public ?Color $shadowColor = null,
    ) {
        foreach (['blur' => $blur, 'saturation' => $saturation, 'light' => $light, 'bevel' => $bevel, 'refraction' => $refraction, 'grain' => $grain, 'frost' => $frost, 'shadow' => $shadow] as $what => $value) {
            if (! is_finite($value)) {
                throw new DrawingException("A glass {$what} is a finite number.");
            }
        }
        if ($blur < 0 || $blur > 1024 || $saturation < 0 || $bevel < 0 || $refraction < 0 || $grain < 0 || $frost < 0 || $frost > 1 || $shadow < 0) {
            throw new DrawingException('A glass blur is 0..1024, its frost 0..1, and its saturation, bevel, refraction, grain and shadow 0 or more.');
        }
    }

    /** The pyramid levels a blur of $radius device pixels takes: 1/2, 1/4, 1/8, and 1/16 for large radii. */
    public static function levels(float $radius): int
    {
        return $radius <= 3.0 ? 1 : ($radius <= 7.0 ? 2 : ($radius <= 15.0 ? 3 : 4));
    }

    /** How far past its box a pane reads, in device pixels: the pyramid's reach and the refraction. */
    public static function margin(float $radius, float $refraction): int
    {
        return (3 << self::levels($radius)) + (int) ceil($refraction);
    }
}
