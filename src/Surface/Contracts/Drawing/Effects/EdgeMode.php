<?php

namespace Surface\Contracts\Drawing\Effects;

/** What lies beyond the edge of the image a filter reads: SVG's edgeMode. */
enum EdgeMode: string
{
    /** Transparent black. */
    case NONE = 'none';

    /** The nearest edge pixel, held. */
    case DUPLICATE = 'duplicate';
}
