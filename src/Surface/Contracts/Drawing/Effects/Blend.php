<?php

namespace Surface\Contracts\Drawing\Effects;

/** SVG's feBlend: the input (on top) blended with $in2 (below) by a mode, on premultiplied colours. */
final readonly class Blend extends FilterStep
{
    public function __construct(public string $in2, public BlendMode $mode = BlendMode::NORMAL, public ?string $in = null, public ?string $result = null) {}

    public function reads(): array
    {
        return [$this->in ?? '', $this->in2];
    }

    public function named(): ?string
    {
        return $this->result;
    }
}
