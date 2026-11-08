<?php

namespace Surface\Contracts\Drawing\Effects;

use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Framebuffers\Framebuffer;

/**
 * SVG's feDisplacementMap, CoreImage's CIDisplacementDistortion: each pixel of the result is the input read at a
 * point moved by the map: P'(x, y) = P(x + scale × (XC − ½), y + scale × (YC − ½)), XC and YC the chosen channels (0..1,
 * straight colour) of the map at (x, y), read with bilinear weights of 1/256. The map starts at the top left of the
 * layer; where it ends, nothing moves.
 *
 * The map is a Framebuffer, or the name of an earlier step's result (a map a Field and a few steps make): then it lies
 * where the layer lies, and a pixel it does not reach moves nothing.
 *
 * With $bands above 1 the colour is split, as a prism splits it: band i of N is read at the point moved by
 * scale × (1 + spread × k), k running from −1 (band 0) to +1 (band N − 1), and each band adds its weights to
 * red, green and blue. Three bands with the default weights are the red, green and blue channels each displaced
 * on its own. The weights of a channel sum to 1, so a flat image stays as it is.
 */
final readonly class Displace extends FilterStep
{
    /** The most bands. */
    public const int MAX_BANDS = 16;

    /**
     * @param  list<array{float, float, float}>|null  $weights  [red, green, blue] for each band; null: lobes for red, green and blue spread along the spectrum.
     *
     * @throws DrawingException When the scale or spread is not finite, the bands are not 1..MAX_BANDS, or the weights are not one triple a band.
     */
    public function __construct(
        public Framebuffer|string $map,
        public float $scale,
        public Channel $xChannel = Channel::R,
        public Channel $yChannel = Channel::G,
        public int $bands = 1,
        public float $spread = 0.0,
        public ?array $weights = null,
        public ?string $in = null,
        public ?string $result = null,
    ) {
        if (! is_finite($scale) || ! is_finite($spread)) {
            throw new DrawingException('A displacement takes a finite scale and spread.');
        }
        if ($bands < 1 || $bands > self::MAX_BANDS) {
            throw new DrawingException('A displacement takes 1 to '.self::MAX_BANDS.' bands.');
        }
        if ($weights !== null) {
            if (count($weights) !== $bands || ! array_is_list($weights)) {
                throw new DrawingException('A displacement takes one [red, green, blue] weight for each band.');
            }
            foreach ($weights as $triple) {
                if (! is_array($triple) || count($triple) !== 3) {
                    throw new DrawingException('A displacement takes one [red, green, blue] weight for each band.');
                }
                foreach ($triple as $w) {
                    if ((! is_int($w) && ! is_float($w)) || ! is_finite((float) $w) || $w < 0) {
                        throw new DrawingException('A band weight is a number of 0 or more.');
                    }
                }
            }
        }
    }

    public function reads(): array
    {
        return is_string($this->map) ? [$this->in ?? '', $this->map] : [$this->in ?? ''];
    }

    public function named(): ?string
    {
        return $this->result;
    }

    public function reach(): float
    {
        return abs($this->scale) * (1.0 + abs($this->spread)) / 2.0 + 1.0;
    }
}
