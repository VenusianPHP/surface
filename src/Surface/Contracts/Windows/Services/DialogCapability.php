<?php

namespace Surface\Contracts\Windows\Services;

/**
 * What a driver's dialogs can do beyond what every driver's do. Dialogs::supports() answers for
 * each; a request that needs one the driver lacks throws WindowException.
 */
enum DialogCapability: string
{
    /** Alert::$title. macOS shows alerts as sheets, which have no title bar. */
    case AlertTitle = 'alert-title';

    /** OpenRequest::$showHidden and SaveRequest::$showHidden. GtkFileDialog has no hidden-files switch. */
    case ShowHidden = 'show-hidden';
}
