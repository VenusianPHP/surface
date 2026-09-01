<?php

namespace Surface\NativeWindows\Views;

use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Contracts\NativeWindows\Views\FontSpec;
use Surface\Contracts\NativeWindows\Views\FontWeight;
use Surface\Contracts\NativeWindows\WindowableException;
use Surface\NativeWindows\Enums\SizeRule;

/**
 * Text styling shared by every text-bearing view. Stores the colour and
 * font Surface believes in; engines translate through two hooks. textCSS()
 * is sugar over the typed setters: recognised declarations route through
 * them, unrecognised ones are ignored — and an engine that cannot honour a
 * hook ignores it there, stated in its own code.
 *
 * Recognised: color, font-size (px), font-weight (name or number),
 * font-family, background-color.
 */
trait StylesText
{
    protected ?Color $text_color = null;

    protected ?FontSpec $font = null;

    public function setTextColor(Color $color): static
    {
        $this->text_color = $color;
        $this->applyTextColor($color);

        return $this;
    }

    public function setFont(float $size, FontWeight $weight = FontWeight::REGULAR, ?string $family = null): static
    {
        $this->font = new FontSpec($size, $weight, $family);
        $this->applyFont($this->font);

        // A hugged view tracks its natural size, and the font just changed
        // it — re-resolve so 64px glyphs are not clipped inside a frame that
        // was measured at 13px.
        if ($this->sizing !== SizeRule::FIXED) {
            $this->relayout();
        }

        return $this;
    }

    public function textCSS(string $css): static
    {
        foreach (explode(';', $css) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }
            [$property, $value] = array_map('trim', explode(':', $declaration, 2));

            match (strtolower($property)) {
                'color' => $this->setTextColor(Color::hex($value)),
                'background-color' => $this->setBackground(Color::hex($value)),
                'font-size' => $this->cssFontSize($value),
                'font-weight' => $this->cssFontWeight($value),
                'font-family' => $this->cssFontFamily($value),
                default => null,
            };
        }

        return $this;
    }

    protected function cssFontSize(string $value): void
    {
        $size = (float) rtrim(strtolower(trim($value)), 'px');
        if ($size <= 0) {
            throw new WindowableException("'{$value}' is not a font size.");
        }
        $this->setFont($size, $this->font?->weight ?? FontWeight::REGULAR, $this->font?->family);
    }

    protected function cssFontWeight(string $value): void
    {
        $value = strtolower(trim($value));
        $weight = FontWeight::tryFrom($value) ?? match (true) {
            $value === 'normal' => FontWeight::REGULAR,
            is_numeric($value) && (int) $value <= 300 => FontWeight::LIGHT,
            is_numeric($value) && (int) $value <= 400 => FontWeight::REGULAR,
            is_numeric($value) && (int) $value <= 500 => FontWeight::MEDIUM,
            is_numeric($value) && (int) $value <= 600 => FontWeight::SEMIBOLD,
            is_numeric($value) && (int) $value <= 700 => FontWeight::BOLD,
            is_numeric($value) => FontWeight::BLACK,
            default => throw new WindowableException("'{$value}' is not a font weight."),
        };
        $this->setFont($this->font?->size ?? 13.0, $weight, $this->font?->family);
    }

    protected function cssFontFamily(string $value): void
    {
        $family = trim(trim($value), "'\"");
        $this->setFont($this->font?->size ?? 13.0, $this->font?->weight ?? FontWeight::REGULAR, $family);
    }

    abstract protected function applyTextColor(Color $color): void;

    abstract protected function applyFont(FontSpec $font): void;
}
