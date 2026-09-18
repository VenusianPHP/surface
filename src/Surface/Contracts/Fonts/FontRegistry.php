<?php

namespace Surface\Contracts\Fonts;

/** Slug → face. One instance per slug; the default slug answers face(null). */
interface FontRegistry
{
    /**
     * @param  class-string<GFXFont>  $class
     * @throws FontException When $class is not a concrete GFXFont.
     */
    public function extend(string $slug, string $class): static;

    /** @throws FontException When the slug is unknown. */
    public function face(?string $slug = null): GFXFont;

    public function has(string $slug): bool;

    /** @return list<string> */
    public function slugs(): array;

    public function defaultSlug(): string;
}
