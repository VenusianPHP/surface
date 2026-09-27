<?php

namespace Surface\Contracts\Canvas;

/** What a Canvas was given to draw into. */
enum CanvasKind: string
{
    case GPU_VIEW = 'gpu-view';
    case GPU_STAGE = 'gpu-stage';
    case CPU_STAGE = 'cpu-stage';
    case EMBEDDED_DISPLAY = 'embedded-display';
}
