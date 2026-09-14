<?php

namespace Surface\Contracts\Drawing;

/**
 * What a GPU engine hands back from attach(): the executor, and — when the
 * engine renders through a layer the window engine must adopt — the layer's
 * raw pointer bits and its class name. layer_pointer 0 means no layer.
 */
final readonly class GPUAttachment
{
    public function __construct(
        public Executor $executor,
        public int $layer_pointer = 0,
        public string $layer_class = '',
    ) {}
}
