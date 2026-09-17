<?php

namespace Surface\Contracts\Framebuffers;

/** An axis-aligned pixel rect, top-left origin. Width or height 0 is empty. */
final readonly class Region
{
    public function __construct(
        public int $x,
        public int $y,
        public int $width,
        public int $height,
    ) {}

    public static function wholeSurface(int $width, int $height): self
    {
        return new self(0, 0, $width, $height);
    }

    public function isEmpty(): bool
    {
        return $this->width <= 0 || $this->height <= 0;
    }

    public function right(): int
    {
        return $this->x + $this->width;
    }

    public function bottom(): int
    {
        return $this->y + $this->height;
    }

    public function contains(int $x, int $y): bool
    {
        return $x >= $this->x && $x < $this->right() && $y >= $this->y && $y < $this->bottom();
    }

    public function intersect(Region $other): ?self
    {
        $x = max($this->x, $other->x);
        $y = max($this->y, $other->y);
        $r = min($this->right(), $other->right());
        $b = min($this->bottom(), $other->bottom());
        if ($r <= $x || $b <= $y) {
            return null;
        }

        return new self($x, $y, $r - $x, $b - $y);
    }

    public function union(Region $other): self
    {
        $x = min($this->x, $other->x);
        $y = min($this->y, $other->y);

        return new self($x, $y, max($this->right(), $other->right()) - $x, max($this->bottom(), $other->bottom()) - $y);
    }

    /** Overlapping or edge/corner adjacent — the merge rule for damage tracking. */
    public function touches(Region $other): bool
    {
        return $this->x <= $other->right() && $other->x <= $this->right()
            && $this->y <= $other->bottom() && $other->y <= $this->bottom();
    }

    /** Expand outward to whole transmit units and clamp to the surface. */
    public function snap(DamageGranularity $g): self
    {
        $x0 = intdiv($this->x, $g->unit_width) * $g->unit_width;
        $y0 = intdiv($this->y, $g->unit_height) * $g->unit_height;
        $x1 = min($g->surface_width, intdiv($this->right() + $g->unit_width - 1, $g->unit_width) * $g->unit_width);
        $y1 = min($g->surface_height, intdiv($this->bottom() + $g->unit_height - 1, $g->unit_height) * $g->unit_height);

        return new self($x0, $y0, $x1 - $x0, $y1 - $y0);
    }
}
