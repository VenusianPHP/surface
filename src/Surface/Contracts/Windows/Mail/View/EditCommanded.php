<?php

declare(strict_types=1);

namespace Surface\Contracts\Windows\Mail\View;

use Surface\Contracts\Windows\Input\EditCommand;

/** The platform's editing binding for a key pressed on a focused canvas. */
readonly class EditCommanded extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public EditCommand $command,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    public function name(): string
    {
        return "view.edit-commanded.{$this->window}.{$this->path}";
    }
}
