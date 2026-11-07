<?php

namespace Surface\Fonts;

use Closure;
use ReflectionClass;
use Surface\Contracts\Fonts\Font;
use Surface\Contracts\Fonts\FontDriver;
use Surface\Contracts\Fonts\FontException;
use Surface\Contracts\Fonts\FontRegistry;
use Surface\Contracts\Fonts\GFXFont;
use Surface\Contracts\Fonts\TrueTypeFont;

/**
 * The face registry: slug → bitmap class or TrueType pattern, one instance per slug. `classic` is always
 * registered first so config may override it. Config is the merged `fonts`
 * array; a companion package extends the registry at boot.
 */
class FontManager implements FontRegistry
{
    /** @var array<string, class-string<GFXFont>> */
    protected array $faces = [];

    /** @var array<string, array{string, float}> slug => [pattern, size] */
    protected array $truetypes = [];

    /** @var array<string, Font> */
    protected array $instances = [];

    protected ?FontDriver $resolved = null;

    protected string $default = 'classic';

    /**
     * @param  array{default?: string, faces?: array<string, array{class?: string, pattern?: string, size?: int|float, enabled?: bool}>}  $config
     * @param  (Closure(): FontDriver)|null  $driver  called once, on the first TrueType face
     */
    public function __construct(array $config = [], protected ?Closure $driver = null)
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
            isset($entry['pattern'])
                ? $this->truetype($slug, (string) $entry['pattern'], (float) ($entry['size'] ?? 13))
                : $this->extend($slug, (string) ($entry['class'] ?? ''));
        }
    }

    public function extend(string $slug, string $class): static
    {
        if (! is_subclass_of($class, GFXFont::class) || (new ReflectionClass($class))->isAbstract()) {
            throw FontException::notAFont($class);
        }
        $key = $this->normalize($slug);
        $this->faces[$key] = $class;
        unset($this->truetypes[$key], $this->instances[$key]);

        return $this;
    }

    public function truetype(string $slug, string $pattern, float $size): static
    {
        $key = $this->normalize($slug);
        $this->truetypes[$key] = [$pattern, $size];
        unset($this->faces[$key], $this->instances[$key]);

        return $this;
    }

    public function face(?string $slug = null): Font
    {
        $key = $this->normalize($slug ?? $this->default);
        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }
        if (isset($this->truetypes[$key])) {
            [$pattern, $size] = $this->truetypes[$key];

            return $this->instances[$key] = new TrueTypeFont($pattern, $size, $this->driver());
        }
        $class = $this->faces[$key] ?? throw FontException::unknown($key);

        return $this->instances[$key] = new $class();
    }

    protected function driver(): FontDriver
    {
        return $this->resolved ??= $this->driver !== null ? ($this->driver)() : throw FontException::extensionsMissing();
    }

    public function has(string $slug): bool
    {
        $key = $this->normalize($slug);

        return isset($this->faces[$key]) || isset($this->truetypes[$key]);
    }

    public function slugs(): array
    {
        return [...array_keys($this->faces), ...array_keys($this->truetypes)];
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
