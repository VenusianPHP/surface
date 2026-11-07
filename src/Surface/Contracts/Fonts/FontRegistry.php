<?php

namespace Surface\Contracts\Fonts;

/** Slug → bitmap or TrueType face. One instance per slug; the default slug answers face(null). */
interface FontRegistry
{
    /**
     * @param  class-string<GFXFont>  $class
     * @throws FontException When $class is not a concrete GFXFont.
     */
    public function extend(string $slug, string $class): static;

    /** A TrueType face: a fontconfig pattern at a size in pixels. */
    public function truetype(string $slug, string $pattern, float $size): static;

    /**
     * @throws FontException When the slug is unknown, or it is TrueType and the font exts are missing.
     */
    public function face(?string $slug = null): Font;

    public function has(string $slug): bool;

    /** @return list<string> */
    public function slugs(): array;

    public function defaultSlug(): string;
}
