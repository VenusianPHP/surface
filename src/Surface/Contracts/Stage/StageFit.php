<?php

namespace Surface\Contracts\Stage;

/**
 * How a host fits a canvas to a differently-sized window. Engine-neutral; a
 * host maps it to its own scaler. INTEGER_SCALE keeps a panel's pixels square
 * and crisp, which is why it is the default.
 */
enum StageFit: string
{
    /** Fill the window, ignore the aspect ratio. */
    case STRETCH = 'stretch';
    /** Keep the aspect ratio, bars on two sides. */
    case LETTERBOX = 'letterbox';
    /** Whole-number zoom only, centred. */
    case INTEGER_SCALE = 'integer_scale';
    /** Keep the aspect ratio, fill the window, crop the overflow. */
    case OVERSCAN = 'overscan';
}
