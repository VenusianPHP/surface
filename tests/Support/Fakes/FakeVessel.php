<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Closure;
use LogicException;
use Voyager\Contracts\Vessel\ContextualBindingBuilder;
use Voyager\Contracts\Vessel\Vessel;

/**
 * A Vessel for constructing the IOPoolDock in tests. With an empty resources
 * config the dock never touches its vessel, so every method is a dead end —
 * reaching one means the test wandered somewhere it should not be.
 */
final class FakeVessel implements Vessel
{
    public function get(string $id): mixed
    {
        throw new \LogicException("FakeVessel cannot resolve '{$id}'.");
    }

    public function has(string $id): bool
    {
        return false;
    }

    public function bound(string $abstract): bool
    {
        return false;
    }

    public function alias(string $abstract, string $alias): void {}

    public function tag(array|string $abstracts, mixed ...$tags): void {}

    public function tagged(string $tag): iterable
    {
        return [];
    }

    public function bind(Closure|string $abstract, Closure|string|null $concrete = null, bool $shared = false): void {}

    public function bindMethod(array|string $method, Closure $callback): void {}

    public function bindIf(Closure|string $abstract, Closure|string|null $concrete = null, bool $shared = false): void {}

    public function singleton(Closure|string $abstract, Closure|string|null $concrete = null): void {}

    public function singletonIf(Closure|string $abstract, Closure|string|null $concrete = null): void {}

    public function scoped(Closure|string $abstract, Closure|string|null $concrete = null): void {}

    public function scopedIf(Closure|string $abstract, Closure|string|null $concrete = null): void {}

    public function extend(Closure|string $abstract, Closure $closure): void {}

    public function instance(Closure|string $abstract, mixed $instance): mixed
    {
        return $instance;
    }

    public function addContextualBinding(string $concrete, Closure|string $abstract, Closure|string $implementation)
    {
    }

    public function when(array|string $concrete): ContextualBindingBuilder
    {
        throw new \LogicException('FakeVessel has no contextual bindings.');
    }

    public function factory(string $abstract): Closure
    {
        throw new \LogicException('FakeVessel has no factories.');
    }

    public function flush(): void {}

    public function make(string $abstract, array $parameters = []): mixed
    {
        throw new LogicException("FakeVessel cannot make '{$abstract}'.");
    }

    public function call(callable|string $callback, array $parameters = [], ?string $defaultMethod = null): mixed
    {
        throw new \LogicException('FakeVessel cannot call.');
    }

    public function resolved(string $abstract): bool
    {
        return false;
    }

    public function beforeResolving(Closure|string $abstract, ?Closure $callback = null): void {}

    public function resolving(Closure|string $abstract, ?Closure $callback = null): void {}

    public function afterResolving(Closure|string $abstract, ?Closure $callback = null): void {}
}
