<?php

namespace Surface\Contracts\Drawing\Effects;

use Surface\Contracts\Drawing\DrawingException;

/**
 * Filters run in the order given, as the primitives of an SVG filter do, or the list of a CSS filter: each step reads
 * the result of the one before, unless it names another input ('source', the image the chain starts from, or the name
 * an earlier step gave its result). The chain's result is its last step's. Colours are sRGB values throughout.
 */
final readonly class FilterChain
{
    /** @var list<FilterStep> */
    public array $steps;

    /**
     * @param  list<FilterStep>  $steps
     *
     * @throws DrawingException When there is no step, a result is named 'source' or twice, or a step reads a name no earlier step made.
     */
    public function __construct(array $steps)
    {
        if ($steps === [] || ! array_is_list($steps)) {
            throw new DrawingException('A filter chain is a list of steps, at least one.');
        }
        $known = [FilterStep::SOURCE => true];
        foreach ($steps as $i => $step) {
            if (! $step instanceof FilterStep) {
                throw new DrawingException("Step {$i} of a filter chain is a FilterStep.");
            }
            foreach ($step->reads() as $name) {
                if ($name !== '' && ! isset($known[$name])) {
                    throw new DrawingException("Step {$i} reads '{$name}', which no earlier step made.");
                }
            }
            if (($name = $step->named()) !== null) {
                if (isset($known[$name])) {
                    throw new DrawingException("Step {$i} names its result '{$name}': a name is used once, and 'source' is the image the chain starts from.");
                }
                $known[$name] = true;
            }
        }
        $this->steps = $steps;
    }

    public static function of(FilterStep ...$steps): self
    {
        return new self(array_values($steps));
    }

    /** How far past its own edge a layer must read for this chain, in pixels. */
    public function reach(): float
    {
        return array_sum(array_map(fn (FilterStep $step): float => $step->reach(), $this->steps));
    }
}
