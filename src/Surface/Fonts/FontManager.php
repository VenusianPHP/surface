<?php

namespace Surface\Fonts;

use ReflectionClass;
use Surface\Contracts\Fonts\FontException;
use Surface\Contracts\Fonts\FontRegistry;
use Surface\Contracts\Fonts\GFXFont;

/**
 * The face registry: slug → class, one instance per slug. `classic` is always
 * registered first so config may override it. Config is the merged `fonts`
 * array; a companion package extends the registry at boot.
 */
class FontManager implements FontRegistry
{
    /** @var array<string, class-string<GFXFont>> */
    protected array $faces = [];

    /** @var array<string, GFXFont> */
    protected array $instances = [];

    protected string $default = 'classic';

    /** @param array{default?: string, faces?: array<string, array{class?: string, enabled?: bool}>} $config */
    public function __construct(array $config = [])
    {
        $this->extend('classic', ClassicFont::class);

        $default = $config['default'] ?? null;
        if (is_string($default) && $default !== '') {
            $this->default = $this->normalize($default);
        }

        foreach ($config['faces'] ?? [] as $slug => $entry) {
            if (! is_string($slug) || ! is_array($entry) || ! ($entry['enabled'] ?? false)) {
                continue;
            }
            $this->extend($slug, (string) ($entry['class'] ?? ''));
        }
    }

    public function extend(string $slug, string $class): static
    {
        if (! is_subclass_of($class, GFXFont::class) || (new ReflectionClass($class))->isAbstract()) {
            throw FontException::notAFont($class);
        }
        $key = $this->normalize($slug);
        $this->faces[$key] = $class;
        unset($this->instances[$key]);

        return $this;
    }

    public function face(?string $slug = null): GFXFont
    {
        $key = $this->normalize($slug ?? $this->default);
        $class = $this->faces[$key] ?? throw FontException::unknown($key);

        return $this->instances[$key] ??= new $class();
    }

    public function has(string $slug): bool
    {
        return isset($this->faces[$this->normalize($slug)]);
    }

    public function slugs(): array
    {
        return array_keys($this->faces);
    }

    public function defaultSlug(): string
    {
        return $this->default;
    }

    protected function normalize(string $slug): string
    {
        return strtolower(trim($slug));
    }
}
