<?php

namespace Surface\Contracts\Drawing\Effects;

/** The noise a Noise step makes. */
enum NoiseType: string
{
    /** SVG's feTurbulence type="turbulence": the sum of the absolute values of Perlin noise octaves. */
    case TURBULENCE = 'turbulence';

    /** SVG's feTurbulence type="fractalNoise": the sum of Perlin noise octaves, centred on mid grey. */
    case FRACTAL = 'fractal';

    /** White noise: one pseudo-random byte for each pixel, from an integer hash of its position and the seed; grey, opaque. */
    case HASH = 'hash';
}
