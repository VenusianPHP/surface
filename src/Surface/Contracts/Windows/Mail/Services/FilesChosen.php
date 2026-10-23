<?php

namespace Surface\Contracts\Windows\Mail\Services;

use Voyager\Contracts\Signals\NamedSignal;

/**
 * The answer of Dialogs::openFiles() and saveFile(): absolute paths; [] when cancelled,
 * dismissed, or the parent closed first.
 */
readonly class FilesChosen implements NamedSignal
{
    /**
     * @param string $id
     * @param list<string> $paths
     */
    public function __construct(
        public string $id,
        public array $paths,
    ) {}

    public function name(): string
    {
        return "dialog.files.{$this->id}";
    }
}
