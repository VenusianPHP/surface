<?php

namespace Surface\Text;

/** What text looks like: a fontconfig family, a size in logical pixels, a CSS weight 100..900, italic or not. */
final readonly class TextStyle
{
    private const array WEIGHTS = [100 => 'thin', 200 => 'extralight', 300 => 'light', 400 => 'regular', 500 => 'medium', 600 => 'demibold', 700 => 'bold', 800 => 'extrabold', 900 => 'black'];

    public function __construct(
        public string $family = 'sans-serif',
        public float $size = 13.0,
        public int $weight = 400,
        public bool $italic = false,
    ) {}

    /** The fontconfig name, such as 'Menlo:weight=bold:slant=italic'. */
    public function pattern(): string
    {
        $weight = self::WEIGHTS[(int) (round(min(900, max(100, $this->weight)) / 100) * 100)];

        return "{$this->family}:weight={$weight}".($this->italic ? ':slant=italic' : '');
    }
}
