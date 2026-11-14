<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKSegmentedControl as PrimitiveContract;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\WindowException;

/**
 * selected is -1 exactly when there are no segments. The driver posts SelectionChanged
 * (option = the segment's label) from the native signal, right after nativeSelected().
 */
abstract class TKSegmentedControl extends TKPrimitive implements PrimitiveContract
{
    /**
     * @var list<string>
     */
    protected array $segments;

    protected int $selected;

    /**
     * @param list<string> $segments
     * @param int $selected ignored when there are no segments
     * @throws WindowException When the name is not valid, the segments are not a list of strings, or $selected is out of range.
     */
    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        array $segments,
        int $selected,
    ) {
        $this->segments = self::guardSegments($segments);
        $this->selected = $segments === [] ? -1 : $this->guardIndex($selected);
        parent::__construct($name, $window, $parent, $placement);
    }

    public function segments(): array
    {
        return $this->segments;
    }

    public function setSegments(array $segments): static
    {
        $this->live();
        $this->segments = self::guardSegments($segments);
        $this->selected = match (true) {
            $segments === [] => -1,
            $this->selected >= 0 && $this->selected < count($segments) => $this->selected,
            default => 0,
        };
        $this->applySegments($this->segments);
        $this->applySelected($this->selected);

        return $this;
    }

    public function setSegment(int $index, string $label): static
    {
        $this->live();
        $this->segments[$this->guardIndex($index)] = $label;
        $this->applySegment($index, $label);

        return $this;
    }

    public function selected(): int
    {
        return $this->selected;
    }

    public function select(int $index): static
    {
        $this->live();
        $this->selected = $this->guardIndex($index);
        $this->applySelected($index);

        return $this;
    }

    /**
     * Engine callback: record the user's choice, post nothing, write nothing back.
     */
    public function nativeSelected(int $index): void
    {
        $this->selected = $index;
    }

    /**
     * @param array<mixed> $segments
     * @return list<string>
     * @throws WindowException
     */
    protected static function guardSegments(array $segments): array
    {
        if (! array_is_list($segments) || array_filter($segments, fn (mixed $segment): bool => ! is_string($segment)) !== []) {
            throw new WindowException('Segments must be a list of strings.');
        }

        return $segments;
    }

    /**
     * @throws WindowException When outside the segments.
     */
    protected function guardIndex(int $index): int
    {
        if ($index < 0 || $index >= count($this->segments)) {
            throw new WindowException("Segment {$index} is out of range (".count($this->segments).' segments).');
        }

        return $index;
    }

    /**
     * Replace the native's segments. applySelected() follows with the kept selection.
     * @param list<string> $segments
     */
    abstract protected function applySegments(array $segments): void;

    abstract protected function applySegment(int $index, string $label): void;

    /**
     * @param int $index -1 = nothing selected (no segments)
     */
    abstract protected function applySelected(int $index): void;
}
