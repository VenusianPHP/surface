<?php

namespace Surface\Canvas\Outputs;

use Surface\Contracts\Canvas\CanvasException;
use Surface\Contracts\Canvas\CanvasKind;
use Surface\Contracts\Stage\CPUStagedWindow;
use Surface\Contracts\Stage\StagedWindow;

/** An engine-owned window, GPU or CPU. A stage has no hide: a shown stage stays shown until it closes. */
final class StageOutput implements Output
{
    public function __construct(private readonly StagedWindow $stage) {}

    public function kind(): CanvasKind
    {
        return $this->stage instanceof CPUStagedWindow ? CanvasKind::CPU_STAGE : CanvasKind::GPU_STAGE;
    }

    /** A CPU stage draws in canvas pixels, whatever size its window is. */
    public function size(): array
    {
        return $this->stage instanceof CPUStagedWindow ? $this->stage->canvasSize() : $this->stage->size();
    }

    public function show(): void
    {
        $this->stage->show();
    }

    public function hide(): void
    {
        throw CanvasException::unsupported('hide', $this->kind());
    }

    public function isVisible(): bool
    {
        return $this->stage->isOpen() && $this->stage->isShown();
    }

    public function close(): void
    {
        $this->stage->close();
    }

    public function isOpen(): bool
    {
        return $this->stage->isOpen();
    }
}
