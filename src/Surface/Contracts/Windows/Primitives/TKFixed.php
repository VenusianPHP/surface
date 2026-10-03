<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Windows\WindowException;

/**
 * Children at pixel frames: the only place pixels exist. at() sets the frame the
 * next creation call uses; creating without one throws WindowException.
 */
interface TKFixed extends TKPrimitiveGroup
{
    /**
     * @param int $x
     * @param int $y
     * @param int $width
     * @param int $height
     * @return $this
     * @throws WindowException When width or height is below 1.
     */
    public function at(int $x, int $y, int $width, int $height): static;

    /**
     * @param TKPrimitive $child
     * @param int $x
     * @param int $y
     * @return $this
     * @throws WindowException When the child is not here.
     */
    public function move(TKPrimitive $child, int $x, int $y): static;

    /**
     * @param TKPrimitive $child
     * @param int $width
     * @param int $height
     * @return $this
     * @throws WindowException When the child is not here, or width or height is below 1.
     */
    public function resize(TKPrimitive $child, int $width, int $height): static;

    /**
     * The child's current frame: its at() frame, then every move() and resize() since.
     * @param TKPrimitive $child
     * @return Placement
     * @throws WindowException When the child is not here.
     */
    public function frameOf(TKPrimitive $child): Placement;
}
