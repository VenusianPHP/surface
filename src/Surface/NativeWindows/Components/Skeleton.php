<?php

namespace Surface\NativeWindows\Components;

use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\NativeWindows\Windowable;

/**
 * A painted placeholder block. CIRCLE is still a square group — it only
 * documents intent until an engine can clip to an oval. No inner parts.
 */
class Skeleton extends Component
{
    public function __construct(
        Windowable $window,
        string $name,
        int $x,
        int $y,
        int $width,
        int $height,
        protected SkeletonShape $shape = SkeletonShape::RECTANGLE,
        ?OSGroup $in = null,
    ) {
        parent::__construct($window, $name, $x, $y, $width, $height, $in);
    }

    protected function build(): void
    {
        $this->root->setBackground(Color::hex('#e5e7eb'));
    }

    protected function layout(): void
    {
        // The root is the placeholder.
    }

    public function shape(): SkeletonShape
    {
        return $this->shape;
    }
}
