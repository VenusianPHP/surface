<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Windows\WindowException;

/**
 * One per window: dotted path → primitive, uuid → primitive.
 */
final class PrimitiveRegistry
{
    /**
     * @var array<string, TKPrimitive>
     */
    private array $by_path = [];

    /**
     * @var array<string, TKPrimitive>
     */
    private array $by_uuid = [];

    /**
     * Whether $name can name a primitive: `[A-Za-z0-9_-]+`, so a path splits on dots unambiguously.
     *
     * @param string $name
     * @return bool
     */
    public static function validName(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9_-]+$/D', $name) === 1;
    }

    /**
     * @param string $name
     * @return void
     * @throws WindowException When the name is not valid.
     */
    public static function guardName(string $name): void
    {
        if (! self::validName($name)) {
            throw new WindowException("Primitive name '{$name}' must match [A-Za-z0-9_-]+ (no dots).");
        }
    }

    /**
     * @param TKPrimitive $primitive
     * @return void
     * @throws WindowException When the path is taken.
     */
    public function register(TKPrimitive $primitive): void
    {
        if (isset($this->by_path[$primitive->path()])) {
            throw new WindowException("Path '{$primitive->path()}' is already registered.");
        }

        $this->by_path[$primitive->path()] = $primitive;
        $this->by_uuid[$primitive->uuid()] = $primitive;
    }

    /**
     * Drop the primitive's path and uuid.
     *
     * @param TKPrimitive $primitive
     * @return void
     */
    public function forget(TKPrimitive $primitive): void
    {
        unset($this->by_path[$primitive->path()], $this->by_uuid[$primitive->uuid()]);
    }

    /**
     * @param string $path
     * @return TKPrimitive|null
     */
    public function byPath(string $path): ?TKPrimitive
    {
        return $this->by_path[$path] ?? null;
    }

    /**
     * @param string $uuid
     * @return TKPrimitive|null
     */
    public function byUuid(string $uuid): ?TKPrimitive
    {
        return $this->by_uuid[$uuid] ?? null;
    }

    /**
     * @return list<TKPrimitive> in registration order
     */
    public function all(): array
    {
        return array_values($this->by_path);
    }
}
