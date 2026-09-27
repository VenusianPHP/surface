<?php

namespace Surface\EmbeddedDisplays\MagicAliases;

use Voyager\MagicAliases\MagicAlias;

/**
 * @method static \Surface\EmbeddedDisplays\EmbeddedDisplay attach(\GeneralPurposeIO\Contracts\IntegratedCircuits\DisplayPanel&\Surface\Contracts\Framebuffers\FormatSpecification $panel, string $name, \Surface\Contracts\Drawing\CPUEngine|string|null $engine = null, \Surface\Contracts\Drawing\CPUHost|null $host = null)
 * @method static \Surface\EmbeddedDisplays\EmbeddedDisplay panel(string $panel, string|null $config = null, string|null $name = null, \Surface\Contracts\Drawing\CPUEngine|string|null $engine = null, \Surface\Contracts\Drawing\CPUHost|null $host = null)
 * @method static void detach(string $name)
 * @method static \Surface\EmbeddedDisplays\EmbeddedDisplay display(string $name)
 * @method static bool has(string $name)
 * @method static array displays()
 * @method static void destroy()
 *
 * @see \Surface\EmbeddedDisplays\EmbeddedDisplayManager
 */
class EmbeddedDisplay extends MagicAlias
{
    protected static function getMagicAliasAccessor(): string
    {
        return 'embedded-displays';
    }
}
