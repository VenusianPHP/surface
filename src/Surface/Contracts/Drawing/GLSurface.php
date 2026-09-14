<?php

namespace Surface\Contracts\Drawing;

/**
 * What a host lends a GL engine: make the context current before a frame,
 * present after it, and answer the drawable size in pixels. The host owns
 * the context; the engine owns everything inside it.
 */
interface GLSurface
{
    public function makeCurrent(): void;

    public function present(): void;

    /** @return array{int, int} pixels */
    public function drawableSize(): array;
}
