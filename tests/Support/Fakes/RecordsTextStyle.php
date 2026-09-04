<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Contracts\NativeWindows\Views\FontSpec;

/** The StylesText engine hooks, recorded. */
trait RecordsTextStyle
{
    /** @var list<Color> */
    public array $applied_text_colors = [];

    /** @var list<FontSpec> */
    public array $applied_fonts = [];

    protected function applyTextColor(Color $color): void
    {
        $this->applied_text_colors[] = $color;
    }

    protected function applyFont(FontSpec $font): void
    {
        $this->applied_fonts[] = $font;
    }
}
