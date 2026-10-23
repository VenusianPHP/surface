<?php

namespace Surface\Contracts\Windows\Mail;

/**
 * The user asked to close a window that confirms close: a staged window opened with
 * confirm_close, or a toolkit window after confirmClose(). It stays open until close().
 */
readonly class WindowCloseRequested extends WindowEventOccurred implements WindowMail
{

    public function name(): string
    {
        return "window.close-requested.{$this->window}";
    }
}
