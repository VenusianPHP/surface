<?php

namespace Surface\Contracts\Windows\Services;

use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\WindowException;

/**
 * Alerts and file panels, modal to a parent window. Each call returns a request id; the answer
 * comes back as mail that carries it (Mail\Services\AlertAnswered, Mail\Services\FilesChosen).
 * The call itself posts nothing.
 */
interface Dialogs
{
    public function supports(DialogCapability $capability): bool;

    /**
     * @throws WindowException When the alert has a title and the driver lacks DialogCapability::AlertTitle;
     *                         when the parent is closed, is not a window of this driver, or has an
     *                         open child or an open dialog.
     */
    public function alert(ToolkitWindow $parent, Alert $alert): string;

    /** @throws WindowException With showHidden when the driver lacks DialogCapability::ShowHidden; for the parent as alert(). */
    public function openFiles(ToolkitWindow $parent, OpenRequest $request): string;

    /** @throws WindowException As openFiles(). */
    public function saveFile(ToolkitWindow $parent, SaveRequest $request): string;
}
