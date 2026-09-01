<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * What a view's text is set in. Family null = the platform's system font.
 */
final class FontSpec
{
    public function __construct(
        public readonly float $size,
        public readonly FontWeight $weight = FontWeight::REGULAR,
        public readonly ?string $family = null,
    ) {}
}
