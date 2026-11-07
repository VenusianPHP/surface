<?php

namespace Surface\Contracts\Drawing\Effects;

use Surface\Contracts\Drawing\DrawingException;

/** SVG's feComposite: the input and $in2 combined by an operator, on premultiplied colours. */
final readonly class Composite extends FilterStep
{
    /**
     * @param  string  $in2  The second input: 'source' or an earlier result's name.
     * @param  float  $k1  Arithmetic only: the four numbers of k1 × a × b + k2 × a + k3 × b + k4.
     *
     * @throws DrawingException When a number is not finite.
     */
    public function __construct(
        public string $in2,
        public CompositeOperator $operator = CompositeOperator::OVER,
        public float $k1 = 0.0,
        public float $k2 = 0.0,
        public float $k3 = 0.0,
        public float $k4 = 0.0,
        public ?string $in = null,
        public ?string $result = null,
    ) {
        foreach ([$k1, $k2, $k3, $k4] as $k) {
            if (! is_finite($k)) {
                throw new DrawingException('A composite takes finite numbers.');
            }
        }
    }

    public static function arithmetic(string $in2, float $k1, float $k2, float $k3, float $k4, ?string $in = null, ?string $result = null): self
    {
        return new self($in2, CompositeOperator::ARITHMETIC, $k1, $k2, $k3, $k4, $in, $result);
    }

    public function reads(): array
    {
        return [$this->in ?? '', $this->in2];
    }

    public function named(): ?string
    {
        return $this->result;
    }
}
