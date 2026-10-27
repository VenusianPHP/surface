<?php

namespace Surface\Windows\Services;

use Surface\Contracts\Windows\Mail\Services\ClipboardText;
use Surface\Contracts\Windows\Services\Clipboard;
use Voyager\Contracts\Signals\NamedSignal;

/**
 * The request ids of clipboard reads, one time for every driver: each id gets one ClipboardText.
 * The driver reads and posts.
 */
abstract class ToolkitClipboard implements Clipboard
{
    /** @var array<string, true> */
    private array $pending = [];

    final public function requestText(): string
    {
        $id = bin2hex(random_bytes(8));
        $this->pending[$id] = true;
        $this->readText($id);

        return $id;
    }

    /** The native answered. An id that is not pending (answered already) posts nothing. */
    protected function answerText(string $id, ?string $text): void
    {
        if (! isset($this->pending[$id])) {
            return;
        }
        unset($this->pending[$id]);
        $this->post(new ClipboardText($id, $text));
    }

    /** Read the clipboard's text and answer $id with answerText(), now or later. */
    abstract protected function readText(string $id): void;

    abstract protected function post(NamedSignal $mail): void;
}
