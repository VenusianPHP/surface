<?php

namespace Surface\Contracts\Windows\Mail\Services;

use Voyager\Contracts\Signals\NamedSignal;

/** The answer of Clipboard::requestText(): the clipboard's text, null when it holds none. */
readonly class ClipboardText implements NamedSignal
{
    public function __construct(
        public string $id,
        public ?string $text,
    ) {}

    public function name(): string
    {
        return "clipboard.text.{$this->id}";
    }
}
