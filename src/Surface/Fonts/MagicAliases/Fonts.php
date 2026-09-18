<?php

namespace Surface\Fonts\MagicAliases;

use Voyager\MagicAliases\MagicAlias;

/**
 * @method static \Surface\Fonts\FontManager extend(string $slug, string $class)
 * @method static \Surface\Contracts\Fonts\GFXFont face(?string $slug = null)
 * @method static bool has(string $slug)
 * @method static list<string> slugs()
 * @method static string defaultSlug()
 *
 * @see \Surface\Fonts\FontManager
 */
class Fonts extends MagicAlias
{
    protected static function getMagicAliasAccessor(): string
    {
        return 'fonts';
    }
}
