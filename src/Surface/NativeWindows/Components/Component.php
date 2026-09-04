<?php

namespace Surface\NativeWindows\Components;

use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\Contracts\NativeWindows\Views\OSView;
use Surface\Contracts\NativeWindows\WindowableException;
use Surface\NativeWindows\Windowable;

/**
 * An opinionated shape composed from primitives. Pure PHP — a component
 * carries NO engine code, which is what caps the twin burden at the
 * primitive list forever.
 *
 * Anatomy: every component mounts one root Group and conjures its parts
 * inside it, so part coordinates are component-relative for free (the
 * native parents under the root's surface) and moving the component moves
 * the subtree natively. Part names are window-global as
 * `<component>.<part>`; the component keeps the short names.
 *
 * Lifecycle: constructing mounts the root, then runs build() (conjure
 * parts) and layout() (arrange them) — subclass constructor-promoted
 * state is already assigned when they run, so a subclass only promotes
 * its props and calls parent::__construct() last. place() re-frames the
 * root and re-runs layout(), which is where responsive behaviour (a
 * sidebar collapsing to icons) lives. Removal is terminal for the whole
 * subtree.
 */
abstract class Component
{
    protected OSGroup $root;

    /** @var array<string, OSView> Parts by short name, in conjure order. */
    protected array $parts = [];

    public function __construct(
        protected Windowable $window,
        public readonly string $name,
        int $x,
        int $y,
        int $width,
        int $height,
        ?OSGroup $in = null,
    ) {
        $this->root = $window->group($name, $x, $y, $width, $height, in: $in);
        $this->build();
        $this->layout();
    }

    /**
     * Conjure the parts into the root. Runs once, from the subclass ctor.
     * @return void
     */
    abstract protected function build(): void;

    /**
     * Arrange the parts against the current root frame. Runs after build()
     * and after every place() — the component's responsive truth.
     * @return void
     */
    abstract protected function layout(): void;

    public function root(): OSGroup
    {
        return $this->root;
    }

    /** A part by its short name, or null. */
    public function part(string $part): ?OSView
    {
        return $this->parts[$part] ?? null;
    }

    /**
     * Move a part, component-relative — 10,10 is 10,10 inside this
     * component, never the window. The size stays.
     * @throws WindowableException When no such part exists.
     */
    public function move(string $part, int $x, int $y): static
    {
        $view = $this->parts[$part] ?? throw new WindowableException(
            "Component '{$this->name}' has no part '{$part}'."
        );

        $frame = $view->frame();
        $view->place($x, $y, $frame['width'], $frame['height']);

        return $this;
    }

    /**
     * Re-frame the component and re-arrange its parts. Window-relative —
     * or relative to the group it was conjured into.
     */
    public function place(int $x, int $y, int $width, int $height): static
    {
        $this->root->place($x, $y, $width, $height);
        $this->layout();

        return $this;
    }

    /** @return array{x: int, y: int, width: int, height: int} */
    public function frame(): array
    {
        return $this->root->frame();
    }

    public function setBackground(Color $color): static
    {
        $this->root->setBackground($color);

        return $this;
    }

    /** Show or hide the whole component — the root takes the subtree. */
    public function setVisible(bool $visible): static
    {
        $this->root->setVisible($visible);

        return $this;
    }

    public function isVisible(): bool
    {
        return $this->root->isVisible();
    }

    public function show(): static
    {
        return $this->setVisible(true);
    }

    public function hide(): static
    {
        return $this->setVisible(false);
    }

    /** Terminal: the root and every part die, and their names are freed. */
    public function remove(): void
    {
        $this->root->remove();
        $this->parts = [];
    }

    /** The window-global name a part registers under. */
    protected function partName(string $part): string
    {
        return "{$this->name}.{$part}";
    }

    /** Record a conjured part under its short name. */
    protected function register(string $part, OSView $view): OSView
    {
        $this->parts[$part] = $view;

        return $view;
    }

    /** The root frame's inner size. */
    protected function innerSize(): array
    {
        return $this->root->innerSize();
    }
}
