<?php

namespace Surface\Contracts\Drawing\Effects;

/**
 * One step of a FilterChain, as an SVG filter primitive is: it reads an input and makes a result. The input is the
 * result of the step before (null), the image the chain starts from ('source'), or an earlier result by its name;
 * the result may be given a name for later steps to read.
 */
abstract readonly class FilterStep
{
    /** The image a chain starts from. */
    public const string SOURCE = 'source';

    /** @var list<string> The names of the inputs the step reads: its $in, and any more it has. */
    abstract public function reads(): array;

    /** The step's own name for what it makes, or null. */
    abstract public function named(): ?string;

    /**
     * How far, in pixels, what the step makes at a pixel can come from: a blur's radius, a displacement's scale.
     * A chain's layer reads this far past its edge.
     */
    public function reach(): float
    {
        return 0.0;
    }
}
