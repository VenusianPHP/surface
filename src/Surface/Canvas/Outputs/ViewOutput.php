<?php

namespace Surface\Canvas\Outputs;

use Surface\Contracts\Canvas\CanvasKind;
use Surface\Contracts\NativeWindows\Views\OSGPUView;

/** A GPU region inside a native window. close() removes the view, which is terminal. */
final class ViewOutput implements Output
{
    private bool $open = true;

    public function __construct(private readonly OSGPUView $view) {}

    public function kind(): CanvasKind
    {
        return CanvasKind::GPU_VIEW;
    }

    public function size(): array
    {
        $frame = $this->view->frame();

        return [$frame['width'], $frame['height']];
    }

    public function show(): void
    {
        $this->view->show();
    }

    public function hide(): void
    {
        $this->view->hide();
    }

    public function isVisible(): bool
    {
        return $this->open && $this->view->isVisible();
    }

    public function close(): void
    {
        if (! $this->open) {
            return;
        }

        $this->open = false;
        $this->view->remove();
    }

    public function isOpen(): bool
    {
        return $this->open;
    }
}
