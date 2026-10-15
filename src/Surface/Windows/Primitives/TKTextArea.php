<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKTextArea as PrimitiveContract;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\ScrollPosition;
use Surface\Contracts\Windows\Styling\TextRangeStyle;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\WindowException;
use Surface\NutsAndBolts\Color;

/**
 * The driver posts TextChanged from the native signal, right after nativeValueChanged(), and
 * ViewScrolled (latest-only) from the native's scroll signal while isWatchingScroll(). Styles
 * reach the driver already fitted to the text: lines inside it, runs clipped to their line.
 */
abstract class TKTextArea extends TKPrimitive implements PrimitiveContract
{
    protected ?FontSpec $font = null;

    protected ?Color $text_color = null;

    protected bool $read_only = false;

    protected bool $wrap = true;

    /** @var array<int, Color> */
    protected array $line_backgrounds = [];

    /** @var list<TextRangeStyle> */
    protected array $range_styles = [];

    protected bool $watching_scroll = false;

    private ?TextLines $lines = null;

    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        protected string $value,
    ) {
        $this->value = self::normalized($value);
        parent::__construct($name, $window, $parent, $placement);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->live();
        $this->value = self::normalized($value);
        $this->lines = null;
        $had_lines = $this->line_backgrounds !== [];
        $had_ranges = $this->range_styles !== [];
        $this->line_backgrounds = [];
        $this->range_styles = [];
        $this->applyValue($this->value);
        if ($had_lines) {
            $this->applyLineBackgrounds([]);
        }
        if ($had_ranges) {
            $this->applyRangeStyles([]);
        }

        return $this;
    }

    public function setFont(FontSpec $font): static
    {
        $this->live();
        $this->font = $font;
        $this->applyFont($font);

        return $this;
    }

    public function setTextColor(?Color $color): static
    {
        $this->live();
        $this->text_color = $color;
        $this->applyTextColor($color);

        return $this;
    }

    public function setReadOnly(bool $read_only): static
    {
        $this->live();
        $this->read_only = $read_only;
        $this->applyReadOnly($read_only);

        return $this;
    }

    public function isReadOnly(): bool
    {
        return $this->read_only;
    }

    public function setWrap(bool $wrap): static
    {
        $this->live();
        $this->wrap = $wrap;
        $this->applyWrap($wrap);

        return $this;
    }

    public function wraps(): bool
    {
        return $this->wrap;
    }

    /**
     * @param array<int, Color> $backgrounds
     * @throws WindowException When a key is not a line number or a value not a Color.
     */
    public function setLineBackgrounds(array $backgrounds): static
    {
        $this->live();
        $count = $this->lines()->count();
        $fitted = [];
        foreach ($backgrounds as $line => $color) {
            if (! is_int($line) || $line < 0 || ! $color instanceof Color) {
                throw new WindowException("Text area '{$this->path()}' line backgrounds map 0-based line numbers to Colors.");
            }
            if ($line < $count) {
                $fitted[$line] = $color;
            }
        }
        ksort($fitted);
        $this->line_backgrounds = $fitted;
        $this->applyLineBackgrounds($fitted);

        return $this;
    }

    /**
     * @param list<TextRangeStyle> $styles
     * @throws WindowException When an entry is not a TextRangeStyle.
     */
    public function setRangeStyles(array $styles): static
    {
        $this->live();
        $lines = $this->lines();
        $fitted = [];
        foreach ($styles as $style) {
            if (! $style instanceof TextRangeStyle) {
                throw new WindowException("Text area '{$this->path()}' range styles must be TextRangeStyle objects.");
            }
            if ($style->line >= $lines->count()) {
                continue;
            }
            $length = min($style->length, $lines->length($style->line) - $style->start);
            if ($length <= 0) {
                continue;
            }
            $fitted[] = $length === $style->length
                ? $style
                : new TextRangeStyle($style->line, $style->start, $length, $style->foreground, $style->background);
        }
        $this->range_styles = $fitted;
        $this->applyRangeStyles($fitted);

        return $this;
    }

    public function lineCount(): int
    {
        return $this->lines()->count();
    }

    public function caretLine(): int
    {
        return $this->selectedLines()[0];
    }

    public function selectedLines(): array
    {
        $last = $this->live()->lines()->count() - 1;
        [$first, $end] = $this->nativeSelectedLines();

        return [min($first, $last), min($end, $last)];
    }

    /**
     * @throws WindowException When the lines are not 0 <= first <= last.
     */
    public function selectLines(int $first, int $last): static
    {
        $this->live();
        if ($first < 0 || $last < $first) {
            throw new WindowException("Text area '{$this->path()}' selects lines 0 <= first <= last, got {$first}-{$last}.");
        }
        $end = $this->lines()->count() - 1;
        $this->applySelectLines(min($first, $end), min($last, $end));

        return $this;
    }

    public function scrollPosition(): ScrollPosition
    {
        return $this->live()->nativeScrollPosition();
    }

    /**
     * @throws WindowException When a fraction is not finite.
     */
    public function scrollTo(float $x, float $y): static
    {
        $this->live();
        if (! is_finite($x) || ! is_finite($y)) {
            throw new WindowException("Text area '{$this->path()}' scroll position must be finite.");
        }
        $this->applyScrollTo(max(0.0, min(1.0, $x)), max(0.0, min(1.0, $y)));

        return $this;
    }

    /**
     * @throws WindowException When the line is negative.
     */
    public function scrollToLine(int $line): static
    {
        $this->live();
        if ($line < 0) {
            throw new WindowException("Text area '{$this->path()}' scrolls to a line from 0, got {$line}.");
        }
        $this->applyScrollToLine(min($line, $this->lines()->count() - 1));

        return $this;
    }

    public function watchScroll(bool $on = true): static
    {
        $this->live();
        $this->watching_scroll = $on;

        return $this;
    }

    public function isWatchingScroll(): bool
    {
        return $this->watching_scroll;
    }

    /**
     * Engine callback: record what the user typed, post nothing, write nothing back. Styles stay
     * where the native anchored them.
     *
     * @param string $value
     * @return void
     */
    public function nativeValueChanged(string $value): void
    {
        $this->value = $value;
        $this->lines = null;
    }

    /**
     * The value's lines, for drivers that address text in UTF-16.
     */
    protected function lines(): TextLines
    {
        return $this->lines ??= new TextLines($this->value);
    }

    private static function normalized(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }

    abstract protected function applyValue(string $value): void;

    abstract protected function applyFont(FontSpec $font): void;

    /**
     * @param Color|null $color null restores the toolkit's own
     */
    abstract protected function applyTextColor(?Color $color): void;

    abstract protected function applyReadOnly(bool $read_only): void;

    abstract protected function applyWrap(bool $wrap): void;

    /**
     * @param array<int, Color> $backgrounds lines inside the text, ascending; [] clears
     */
    abstract protected function applyLineBackgrounds(array $backgrounds): void;

    /**
     * @param list<TextRangeStyle> $styles each inside its line, length > 0; [] clears
     */
    abstract protected function applyRangeStyles(array $styles): void;

    abstract protected function applySelectLines(int $first, int $last): void;

    /**
     * @param float $x 0..1
     * @param float $y 0..1
     */
    abstract protected function applyScrollTo(float $x, float $y): void;

    abstract protected function applyScrollToLine(int $line): void;

    /**
     * @return array{int, int}
     */
    abstract protected function nativeSelectedLines(): array;

    abstract protected function nativeScrollPosition(): ScrollPosition;
}
