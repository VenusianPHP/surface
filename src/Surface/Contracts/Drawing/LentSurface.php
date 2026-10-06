<?php

namespace Surface\Contracts\Drawing;

use Closure;

/**
 * A native surface a window has lent: its kind, its handles as addresses, and
 * its size in pixels read live from the window. The window releases it when
 * it reclaims the surface, before the native surface goes; a released surface
 * must not be presented into. A borrower registers with onRelease() what it
 * must do while the native surface still exists (a GL device frees its objects
 * in the lent context).
 */
final class LentSurface
{
    private bool $released = false;

    /** @var list<Closure(): void> */
    private array $on_release = [];

    /**
     * @param  array<string, int>  $handles  Native addresses by name; always the kind's handle().
     * @param  Closure(): array{int, int}  $size  The surface's size in device pixels, now.
     */
    public function __construct(
        public readonly SurfaceKind $kind,
        private readonly array $handles,
        private readonly Closure $size,
    ) {
        if (! isset($handles[$kind->handle()])) {
            throw new DrawingException("A {$kind->value} surface carries a '{$kind->handle()}' handle.");
        }
    }

    /** @throws DrawingException When the surface has no handle of that name. */
    public function handle(string $name): int
    {
        return $this->handles[$name]
            ?? throw new DrawingException("This {$this->kind->value} surface has no '{$name}' handle (it has: ".implode(', ', array_keys($this->handles)).').');
    }

    /** @return array<string, int> */
    public function handles(): array
    {
        return $this->handles;
    }

    /** @return array{int, int} width, height in device pixels */
    public function size(): array
    {
        return ($this->size)();
    }

    public function released(): bool
    {
        return $this->released;
    }

    /**
     * Called by the window when it reclaims the surface, while the native
     * surface still exists: marks it released, then runs what borrowers
     * registered, once, in the order registered.
     */
    public function release(): void
    {
        if ($this->released) {
            return;
        }
        $this->released = true;
        $then = $this->on_release;
        $this->on_release = [];
        foreach ($then as $closure) {
            $closure();
        }
    }

    /**
     * Run $then when the window releases the surface, before the native surface
     * goes; at once when it is already released.
     *
     * @param  Closure(): void  $then
     */
    public function onRelease(Closure $then): void
    {
        if ($this->released) {
            $then();

            return;
        }
        $this->on_release[] = $then;
    }
}
