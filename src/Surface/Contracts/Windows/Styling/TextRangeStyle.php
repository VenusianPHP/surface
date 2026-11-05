<?php

namespace Surface\Contracts\Windows\Styling;

use InvalidArgumentException;
use Surface\NutsAndBolts\Color;

/**
 * Colours and flags for a run of characters on one line of a text area: start and length in
 * code points of that line. A text area clips a run to its line and drops runs outside the text.
 */
final readonly class TextRangeStyle
{
    /**
     * @param int $line 0-based
     * @param int $start code points into the line
     * @param int $length code points
     * @param Color|null $foreground
     * @param Color|null $background
     * @param bool $bold
     * @param bool $italic
     * @param bool $underline a single line
     * @param bool $strikeout a single line
     * @throws InvalidArgumentException When a number is negative, or the run has no colour and no flag.
     */
    public function __construct(
        public int $line,
        public int $start,
        public int $length,
        public ?Color $foreground = null,
        public ?Color $background = null,
        public bool $bold = false,
        public bool $italic = false,
        public bool $underline = false,
        public bool $strikeout = false,
    ) {
        if ($line < 0 || $start < 0 || $length < 0) {
            throw new InvalidArgumentException("A text range's line, start and length are not negative, got {$line}, {$start}, {$length}.");
        }
        if (is_null($foreground) && is_null($background) && ! $this->hasFlags()) {
            throw new InvalidArgumentException('A text range style needs a colour or a flag.');
        }
    }

    public function hasFlags(): bool
    {
        return $this->bold || $this->italic || $this->underline || $this->strikeout;
    }
}
