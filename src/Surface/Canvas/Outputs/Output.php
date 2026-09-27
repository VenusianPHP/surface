<?php

namespace Surface\Canvas\Outputs;

use Surface\Contracts\Canvas\CanvasKind;

/** What differs between the things a Canvas draws into: their size and their lifecycle verbs. */
interface Output
{
    public function kind(): CanvasKind;

    /** @return array{int, int} the space Drawing2D coordinates live in */
    public function size(): array;

    public function show(): void;

    public function hide(): void;

    public function isVisible(): bool;

    public function close(): void;

    public function isOpen(): bool;
}
