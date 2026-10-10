<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Align;
use Surface\Contracts\Windows\Primitives\HasEnabledState;
use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\PrimitiveRegistry;
use Surface\Contracts\Windows\Primitives\TKPrimitive as PrimitiveContract;
use Surface\Contracts\Windows\Menus\ContextMenu as ContextMenuContract;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\WindowException;
use Surface\NutsAndBolts\Color;
use Surface\Windows\Menus\ContextMenu;
use Voyager\NutsAndBolts\Str;

/**
 * State, identity and the removed guard every primitive shares. The driver's concrete
 * writes each change to its native through the apply* hooks and reads sizes back
 * through nativeSize(); it never posts mail from a hook. Abstracts never call a hook from
 * their constructor: a concrete calls parent::__construct() first (which validates the name
 * and the kind's arguments) and then builds its native from the constructed state.
 */
abstract class TKPrimitive implements PrimitiveContract
{
    use HasEnabledState {
        setEnabled as private storeEnabled;
    }

    protected readonly string $uuid;

    protected bool $removed = false;

    protected bool $visible = true;

    protected bool $watching_size = false;

    protected ?Color $background = null;

    protected ?ContextMenuContract $context_menu = null;

    protected bool $fill_horizontal = false;

    protected bool $fill_vertical = false;

    protected Align $align_horizontal = Align::FILL;

    protected Align $align_vertical = Align::FILL;

    /**
     * @var array{int, int}|null
     */
    protected ?array $min_size = null;

    /**
     * @param string $name
     * @param ToolkitWindow $window
     * @param TKPrimitiveGroup|null $parent null for the window's content container
     * @param Placement $placement
     * @throws WindowException When the name is not valid.
     */
    public function __construct(
        protected readonly string $name,
        protected readonly ToolkitWindow $window,
        protected readonly ?TKPrimitiveGroup $parent,
        protected readonly Placement $placement,
    ) {
        PrimitiveRegistry::guardName($name);
        $this->uuid = Str::uuid()->toString();
    }

    public function name(): string
    {
        return $this->name;
    }

    public function path(): string
    {
        return is_null($this->parent) ? $this->name : $this->parent->path().'.'.$this->name;
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    public function window(): ToolkitWindow
    {
        return $this->window;
    }

    public function parent(): ?TKPrimitiveGroup
    {
        return $this->parent;
    }

    public function placement(): Placement
    {
        return $this->placement;
    }

    public function isRemoved(): bool
    {
        return $this->removed;
    }

    /**
     * Terminal: stop size watching, destroy the native, drop the path and uuid, and leave
     * the parent (or the window's content slot).
     *
     * @return void
     * @throws WindowException When already removed.
     */
    public function remove(): void
    {
        $this->live();
        if ($this->watching_size) {
            $this->watching_size = false;
            $this->applyWatchSize(false);
        }
        $this->removed = true;
        $this->destroyNative();
        $this->window->registry()->forget($this);

        if (is_null($this->parent)) {
            $this->window->forgetContent($this);
        } else {
            $this->parent->forgetChild($this);
        }
    }

    public function setVisible(bool $visible): static
    {
        $this->live();
        if ($this->visible !== $visible) {
            $this->visible = $visible;
            $this->applyVisible($visible);
        }

        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function show(): static
    {
        return $this->setVisible(true);
    }

    public function hide(): static
    {
        return $this->setVisible(false);
    }

    public function setEnabled(bool $enabled): static
    {
        return $this->live()->storeEnabled($enabled);
    }

    public function size(): array
    {
        return $this->live()->nativeSize();
    }

    public function watchSize(bool $on = true): static
    {
        $this->live();
        if ($this->watching_size !== $on) {
            $this->watching_size = $on;
            $this->applyWatchSize($on);
        }

        return $this;
    }

    public function isWatchingSize(): bool
    {
        return $this->watching_size;
    }

    /**
     * The driver reads the menu when the view is right-clicked; nothing is written to the native.
     * @throws WindowException
     */
    public function setContextMenu(array|ContextMenuContract|null $menu): static
    {
        $this->live();
        $this->context_menu = is_array($menu) ? ContextMenu::parse($menu) : $menu;

        return $this;
    }

    public function contextMenu(): ?ContextMenuContract
    {
        return $this->context_menu;
    }

    public function setBackground(?Color $color): static
    {
        $this->live();
        $this->background = $color;
        $this->applyBackground($color);

        return $this;
    }

    public function fill(bool $horizontal = true, bool $vertical = true): static
    {
        $this->live();
        $this->fill_horizontal = $horizontal;
        $this->fill_vertical = $vertical;
        $this->applyFill($horizontal, $vertical);

        return $this;
    }

    public function align(Align $horizontal, Align $vertical = Align::FILL): static
    {
        $this->live();
        $this->align_horizontal = $horizontal;
        $this->align_vertical = $vertical;
        $this->applyAlign($horizontal, $vertical);

        return $this;
    }

    public function minSize(int $width, int $height): static
    {
        $this->live();
        if ($width < 0 || $height < 0) {
            throw new WindowException("A minimum size needs width and height >= 0, got {$width}x{$height}.");
        }
        $this->min_size = [$width, $height];
        $this->applyMinSize($width, $height);

        return $this;
    }

    public function moveBefore(PrimitiveContract $sibling): static
    {
        $stack = $this->stack();
        $stack->reorder($this, $stack->positionOf($sibling, $this));

        return $this;
    }

    public function moveAfter(PrimitiveContract $sibling): static
    {
        $stack = $this->stack();
        $stack->reorder($this, $stack->positionOf($sibling, $this) + 1);

        return $this;
    }

    public function moveTo(int $index): static
    {
        $stack = $this->stack();
        $count = count($stack->children());
        if ($index < 0 || $index >= $count) {
            throw new WindowException("Index {$index} is out of range for '{$stack->path()}' (0..".($count - 1).').');
        }
        $stack->reorder($this, $index);

        return $this;
    }

    /**
     * The column or row this primitive reorders in.
     *
     * @return TKColumn|TKRow
     * @throws WindowException Once removed, or when the parent is not a column or row.
     */
    protected function stack(): TKColumn|TKRow
    {
        $this->live();
        if (! $this->parent instanceof TKColumn && ! $this->parent instanceof TKRow) {
            throw new WindowException("'{$this->path()}' is not in a column or row; only their children reorder.");
        }

        return $this->parent;
    }

    /**
     * @return $this
     * @throws WindowException Once removed.
     */
    protected function live(): static
    {
        if ($this->removed) {
            throw new WindowException("Primitive '{$this->path()}' was removed.");
        }

        return $this;
    }

    /**
     * Destroy the native view. Called once, children first.
     * @return void
     */
    abstract protected function destroyNative(): void;

    /**
     * @param bool $visible
     * @return void
     */
    abstract protected function applyVisible(bool $visible): void;

    /**
     * @param Color|null $color null restores the toolkit's own
     * @return void
     */
    abstract protected function applyBackground(?Color $color): void;

    /**
     * @param bool $horizontal
     * @param bool $vertical
     * @return void
     */
    abstract protected function applyFill(bool $horizontal, bool $vertical): void;

    /**
     * @param Align $horizontal
     * @param Align $vertical
     * @return void
     */
    abstract protected function applyAlign(Align $horizontal, Align $vertical): void;

    /**
     * @param int $width
     * @param int $height
     * @return void
     */
    abstract protected function applyMinSize(int $width, int $height): void;

    /**
     * Start or stop reporting this view's size to the session (ViewResized via postLatest).
     * Stopping also forgetLatest()s the view's pending key, so nothing goes out after remove().
     * @param bool $on
     * @return void
     */
    abstract protected function applyWatchSize(bool $on): void;

    /**
     * @return array{int, int} the current allocation, from the toolkit
     */
    abstract protected function nativeSize(): array;
}
