<?php

namespace Surface\Contracts\Framebuffers;

/** How an image's pixels are read when it is drawn at another size or angle. */
enum Filter: string
{
    /** The one source pixel under the sample point: hard pixels, exact colours. */
    case NEAREST = 'nearest';

    /** The four source pixels around the sample point, weighted: smooth. */
    case LINEAR = 'linear';
}
