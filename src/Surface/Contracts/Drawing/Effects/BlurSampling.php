<?php

namespace Surface\Contracts\Drawing\Effects;

/** How a blur reads its neighbours. */
enum BlurSampling: string
{
    /** SVG's feGaussianBlur: three box blurs approximate the Gaussian (the spec's rule; a true Gaussian kernel below a standard deviation of 2). */
    case GAUSSIAN = 'gaussian';

    /** A fixed number of samples on a golden-angle spiral (Vogel's disc) out to the radius, averaged: a bokeh-like disc blur. */
    case SPIRAL = 'spiral';
}
