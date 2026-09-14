<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Closure;
use LogicException;
use Psr\Container\NotFoundExceptionInterface;
use Voyager\Contracts\Vessel\ContextualBindingBuilder;
use Voyager\Contracts\Vessel\Vessel;

/**
 * A Vessel that resolves from a flat map — enough for a Manager: make('config')
 * answers the repository, get('gpu.metal') answers a bound driver, and an
 * unknown id raises the PSR not-found the engine-seam decision accepts.
 */
final class FakeBindingVessel implements Vessel
{
    /** @param array<string, mixed> $bindings */
    public function __construct(public array $bindings = []) {}

    public function get(string $id): mixed
    {
        if (! array_key_exists($id, $this->bindings)) {
            throw new class("'{$id}' is not bound.") extends LogicException implements NotFoundExceptionInterface {};
        }

        return $this->bindings[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->bindings);
    }

    public function bound(string $abstract): bool
    {
        return $this->has($abstract);
    }

    public function make(string $abstract, array $parameters = []): mixed
    {
        return $this->get($abstract);
    }

    public function instance(Closure|string $abstract, mixed $instance): mixed
    {
        $this->bindings[$abstract] = $instance;

        return $instance;
    }

    public function alias(string $abstract, string $alias): void {}
    public function tag(array|string $abstracts, mixed ...$tags): void {}
    public function tagged(string $tag): iterable { return []; }
    public function bind(Closure|string $abstract, Closure|string|null $concrete = null, bool $shared = false): void {}
    public function bindMethod(array|string $method, Closure $callback): void {}
    public function bindIf(Closure|string $abstract, Closure|string|null $concrete = null, bool $shared = false): void {}
    public function singleton(Closure|string $abstract, Closure|string|null $concrete = null): void {}
    public function singletonIf(Closure|string $abstract, Closure|string|null $concrete = null): void {}
    public function scoped(Closure|string $abstract, Closure|string|null $concrete = null): void {}
    public function scopedIf(Closure|string $abstract, Closure|string|null $concrete = null): void {}
    public function extend(Closure|string $abstract, Closure $closure): void {}
    public function addContextualBinding(string $concrete, Closure|string $abstract, Closure|string $implementation) {}
    public function when(array|string $concrete): ContextualBindingBuilder { throw new LogicException('no contextual bindings'); }
    public function factory(string $abstract): Closure { throw new LogicException('no factories'); }
    public function flush(): void { $this->bindings = []; }
    public function call(callable|string $callback, array $parameters = [], ?string $defaultMethod = null): mixed { throw new LogicException('cannot call'); }
    public function resolved(string $abstract): bool { return $this->has($abstract); }
    public function beforeResolving(Closure|string $abstract, ?Closure $callback = null): void {}
    public function resolving(Closure|string $abstract, ?Closure $callback = null): void {}
    public function afterResolving(Closure|string $abstract, ?Closure $callback = null): void {}
}
