<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\ScrollPosition;
use Surface\Contracts\Windows\Styling\TextRangeStyle;
use Surface\NutsAndBolts\Color;

/**
 * Multi-line text the toolkit draws and lays out. Lines are "\n"-separated: setValue turns
 * "\r\n" and "\r" into "\n", so every toolkit counts the same lines. A user's edit can bring
 * "\r\n", "\r" or U+2029 back; each ends one line, as the toolkits count them. Lines and code
 * points address styles and selection. Code changes post no mail; the user's edits post
 * TextChanged, the user's scrolls ViewScrolled while watched.
 */
interface TKTextArea extends TKPrimitive
{
    public function value(): string;

    public function setValue(string $value): static;

    public function setFont(FontSpec $font): static;

    public function setTextColor(?Color $color): static;

    /** Read-only text still selects and copies. */
    public function setReadOnly(bool $read_only): static;

    public function isReadOnly(): bool;

    /** True (the default) wraps at the view's width; false scrolls sideways. */
    public function setWrap(bool $wrap): static;

    public function wraps(): bool;

    /**
     * Full-width backgrounds by 0-based line; replaces the previous set. Lines past the text are ignored.
     * @param array<int, Color> $backgrounds
     */
    public function setLineBackgrounds(array $backgrounds): static;

    /**
     * Character runs' colours; replaces the previous set. Runs are clipped to their line.
     * @param list<TextRangeStyle> $styles
     */
    public function setRangeStyles(array $styles): static;

    public function lineCount(): int;

    /** The first selected line: the insertion point's line when nothing is selected. */
    public function caretLine(): int;

    /**
     * @return array{int, int} first and last selected line, inclusive; a line's "\n" belongs to it
     */
    public function selectedLines(): array;

    public function selectLines(int $first, int $last): static;

    public function scrollPosition(): ScrollPosition;

    /** The visible rect's top-left as content fractions (ScrollPosition's x and y), kept inside the content. */
    public function scrollTo(float $x, float $y): static;

    /** The line at the top of the view, as near as the end of the text allows. */
    public function scrollToLine(int $line): static;

    public function watchScroll(bool $on = true): static;

    public function isWatchingScroll(): bool;
}
