<?php

namespace Surface\Canvas\Outputs;

use Surface\Contracts\Canvas\CanvasKind;
use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplay;

/** An IC display panel. show() and hide() reach the panel's own output switch, where it has one. */
final class DisplayOutput implements Output
{
    public function __construct(private readonly EmbeddedDisplay $display) {}

    public function kind(): CanvasKind
    {
        return CanvasKind::EMBEDDED_DISPLAY;
    }

    public function size(): array
    {
        return $this->display->drawableSize();
    }

    public function show(): void
    {
        $this->display->show();
    }

    public function hide(): void
    {
        $this->display->hide();
    }

    public function isVisible(): bool
    {
        return $this->display->isVisible();
    }

    public function close(): void
    {
        $this->display->close();
    }

    public function isOpen(): bool
    {
        return $this->display->isOpen();
    }
}
