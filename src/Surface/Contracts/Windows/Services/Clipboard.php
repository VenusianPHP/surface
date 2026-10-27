<?php

namespace Surface\Contracts\Windows\Services;

/**
 * The system clipboard, as text. A change of its content, by another app or by setText(), posts
 * Mail\Services\ClipboardChanged (latest-wins).
 */
interface Clipboard
{
    public function setText(string $text): void;

    /** A request id, answered once by Mail\Services\ClipboardText (null text when the clipboard holds none). */
    public function requestText(): string;

    public function hasText(): bool;
}
