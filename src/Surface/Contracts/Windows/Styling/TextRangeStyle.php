<?php

namespace Surface\Contracts\Windows\Styling;

use InvalidArgumentException;
use Surface\NutsAndBolts\Color;

/**
 * Colours for a run of characters on one line of a text area: start and length in code points
 * of that line. A text area clips a run to its line and drops runs outside the text.
 */
final readonly class TextRangeStyle
{
    /**
     * @param int $line 0-based
     * @param int $start code points into the line
     * @param int $length code points
     * @param Color|null $foreground
     * @param Color|null $background
     * @throws InvalidArgumentException When a number is negative or both colours are null.
     */
    public function __construct(
        public int $line,
        public int $start,
        public int $length,
        public ?Color $foreground = null,
        public ?Color $background = null,
    ) {
        if ($line < 0 || $start < 0 || $length < 0) {
            throw new InvalidArgumentException("A text range's line, start and length are not negative, got {$line}, {$start}, {$length}.");
        }
        if (is_null($foreground) && is_null($background)) {
            throw new InvalidArgumentException('A text range style needs a foreground or a background.');
        }
    }
}
