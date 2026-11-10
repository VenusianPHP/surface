<?php

namespace Surface\Contracts\Windows\Primitives;

/**
 * How a primitive appears or disappears when setVisible() changes it (TKPrimitive::appear(), disappear()).
 * Only what every toolkit animates natively: a fade, or a fade with a slide from SLIDE_DISTANCE points away.
 * The toolkit runs the animation; nothing steps it from PHP.
 */
enum TKTransition: string
{
    /** Points a slide starts from (appearing) or ends at (disappearing). */
    public const int SLIDE_DISTANCE = 24;

    case FADE = 'fade';
    case SLIDE_UP = 'slide-up';
    case SLIDE_DOWN = 'slide-down';
    case SLIDE_LEFT = 'slide-left';
    case SLIDE_RIGHT = 'slide-right';

    /**
     * Where the view is drawn from at the start of an appearance, in points, top-left based
     * (SLIDE_UP comes from below, so its y offset is positive).
     *
     * @return array{int, int} x, y
     */
    public function offset(): array
    {
        return match ($this) {
            self::FADE => [0, 0],
            self::SLIDE_UP => [0, self::SLIDE_DISTANCE],
            self::SLIDE_DOWN => [0, -self::SLIDE_DISTANCE],
            self::SLIDE_LEFT => [self::SLIDE_DISTANCE, 0],
            self::SLIDE_RIGHT => [-self::SLIDE_DISTANCE, 0],
        };
    }
}
