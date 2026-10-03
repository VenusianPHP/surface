<?php

namespace Surface\Windows\Primitives;

use Closure;
use Surface\Contracts\Windows\Primitives\PrimitiveFactory;
use Surface\Contracts\Windows\Primitives\PrimitiveRegistry;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKFixed;
use Surface\Contracts\Windows\Primitives\TKGrid;
use Surface\Contracts\Windows\Primitives\TKPrimitive;
use Surface\Contracts\Windows\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Windows\Primitives\TKRow;
use Surface\Contracts\Windows\WindowException;

/**
 * For driver windows: the one content container, the registry and lookups. The window
 * supplies factory() and mountContent(), and calls removeContent() from close() while
 * its native is still alive, so the tree is destroyed before the window that holds it.
 */
trait HostsPrimitives
{
    protected ?TKPrimitiveGroup $content_group = null;

    protected ?PrimitiveRegistry $registry = null;

    /**
     * The driver's minter.
     * @return PrimitiveFactory
     */
    abstract public function factory(): PrimitiveFactory;

    /**
     * Put the content container's native into the window's content area.
     *
     * @param TKPrimitiveGroup $content
     * @return void
     */
    abstract protected function mountContent(TKPrimitiveGroup $content): void;

    abstract public function name(): string;

    abstract public function isOpen(): bool;

    public function registry(): PrimitiveRegistry
    {
        return $this->registry ??= new PrimitiveRegistry();
    }

    public function column(string $name, int $spacing = 0, int $padding = 0): TKColumn
    {
        return $this->mount($name, fn (PrimitiveFactory $factory) => $factory->mintColumn(null, $name, $spacing, $padding));
    }

    public function row(string $name, int $spacing = 0, int $padding = 0): TKRow
    {
        return $this->mount($name, fn (PrimitiveFactory $factory) => $factory->mintRow(null, $name, $spacing, $padding));
    }

    public function grid(string $name, int $spacing = 0, int $padding = 0): TKGrid
    {
        return $this->mount($name, fn (PrimitiveFactory $factory) => $factory->mintGrid(null, $name, $spacing, $padding));
    }

    public function fixed(string $name): TKFixed
    {
        return $this->mount($name, fn (PrimitiveFactory $factory) => $factory->mintFixed(null, $name));
    }

    public function content(): ?TKPrimitiveGroup
    {
        $this->guardOpen();

        return $this->content_group;
    }

    public function view(string $path): ?TKPrimitive
    {
        $this->guardOpen();

        return $this->registry()->byPath($path);
    }

    public function uuid(string $uuid): ?TKPrimitive
    {
        $this->guardOpen();

        return $this->registry()->byUuid($uuid);
    }

    public function forgetContent(TKPrimitiveGroup $content): void
    {
        if ($this->content_group !== $content) {
            throw new WindowException("'{$content->path()}' is not the content of window '{$this->name()}'.");
        }
        if (! $content->isRemoved()) {
            throw new WindowException("'{$content->path()}' is not removed; call its remove().");
        }
        $this->content_group = null;
    }

    /**
     * Admit, mint and mount the content container. Refusals happen before the factory mints.
     *
     * @template T of TKPrimitiveGroup
     * @param string $name
     * @param Closure(PrimitiveFactory): T $mint
     * @return T
     * @throws WindowException When the window is closed, already has content, or the name is invalid.
     */
    protected function mount(string $name, Closure $mint): TKPrimitiveGroup
    {
        $this->guardOpen();
        PrimitiveRegistry::guardName($name);
        if (! is_null($this->content_group)) {
            throw new WindowException("Window '{$this->name()}' already has content container '{$this->content_group->name()}'.");
        }

        $content = $mint($this->factory());
        $this->registry()->register($content);
        $this->content_group = $content;
        $this->mountContent($content);

        return $content;
    }

    /**
     * Remove the whole tree (terminal). Call from close() while the native window lives, then
     * forgetLatest("window.resized.<name>") on the session, then post WindowClosed: each
     * watched view drops its own pending ViewResized as it is removed.
     * @return void
     */
    protected function removeContent(): void
    {
        $this->content_group?->remove();
    }

    /**
     * @return void
     * @throws WindowException When the window is closed.
     */
    protected function guardOpen(): void
    {
        if (! $this->isOpen()) {
            throw new WindowException("Window '{$this->name()}' is closed.");
        }
    }
}
