<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\TextAlignment;

/**
 * Text that opens a URL when clicked: the toolkit opens it (the browser for http and https).
 * Posts no mail. Drawn in the toolkit's link colour.
 */
interface TKLink extends TKPrimitive
{
    /**
     * @return string
     */
    public function text(): string;

    /**
     * @param string $text
     * @return $this
     */
    public function setText(string $text): static;

    /**
     * @return string
     */
    public function url(): string;

    /**
     * @param string $url with a scheme (https://…, mailto:…)
     * @return $this
     */
    public function setUrl(string $url): static;

    /**
     * @param TextAlignment $alignment
     * @return $this
     */
    public function setAlignment(TextAlignment $alignment): static;

    /**
     * @param FontSpec $font
     * @return $this
     */
    public function setFont(FontSpec $font): static;
}
