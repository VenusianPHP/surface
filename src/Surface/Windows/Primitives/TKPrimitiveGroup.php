<?php

namespace Surface\Windows\Primitives;

use Closure;
use DateTimeImmutable;
use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\PrimitiveFactory;
use Surface\Contracts\Windows\Primitives\PrimitiveRegistry;
use Surface\Contracts\Windows\Primitives\TKButton;
use Surface\Contracts\Windows\Primitives\TKCanvas;
use Surface\Contracts\Windows\Primitives\TKCheckbox;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKDatepicker;
use Surface\Contracts\Windows\Primitives\TKDropdown;
use Surface\Contracts\Windows\Primitives\TKFixed;
use Surface\Contracts\Windows\Primitives\TKGrid;
use Surface\Contracts\Windows\Primitives\TKImage;
use Surface\Contracts\Windows\Primitives\TKLabel;
use Surface\Contracts\Windows\Primitives\TKPrimitive as Child;
use Surface\Contracts\Windows\Primitives\TKPrimitiveGroup as PrimitiveContract;
use Surface\Contracts\Windows\Primitives\TKProgressBar;
use Surface\Contracts\Windows\Primitives\TKRow;
use Surface\Contracts\Windows\Primitives\TKScrollView;
use Surface\Contracts\Windows\Primitives\TKSeparator;
use Surface\Contracts\Windows\Primitives\TKSlider;
use Surface\Contracts\Windows\Primitives\TKSpinner;
use Surface\Contracts\Windows\Primitives\TKTable;
use Surface\Contracts\Windows\Primitives\TKTextArea;
use Surface\Contracts\Windows\Primitives\TKTextInput;
use Surface\Contracts\Windows\Primitives\TKToggle;
use Surface\Contracts\Windows\Primitives\TKToggleButton;
use Surface\Contracts\Windows\Primitives\TKVideo;
use Surface\Contracts\Windows\WindowException;

/**
 * A container. Every creation call admits the name and placement first, then asks the
 * window's factory to mint, then registers the child and hands it to insertNative().
 * Nothing native exists for a refused child.
 */
abstract class TKPrimitiveGroup extends TKPrimitive implements PrimitiveContract
{
    /**
     * @var array<string, Child> children by name, in display order
     */
    protected array $children = [];

    /**
     * The cell or frame the next creation call takes (grid and fixed set it with at()).
     */
    protected ?Placement $pending = null;

    public function label(string $name, string $text): TKLabel
    {
        return $this->create($name, false, fn (PrimitiveFactory $factory) => $factory->mintLabel($this, $name, $text));
    }

    public function button(string $name, string $label): TKButton
    {
        return $this->create($name, false, fn (PrimitiveFactory $factory) => $factory->mintButton($this, $name, $label));
    }

    public function image(string $name, ?string $file = null): TKImage
    {
        return $this->create($name, false, fn (PrimitiveFactory $factory) => $factory->mintImage($this, $name, $file));
    }

    public function canvas(string $name): TKCanvas
    {
        return $this->create($name, false, fn (PrimitiveFactory $factory) => $factory->mintCanvas($this, $name));
    }

    public function separator(string $name, bool $horizontal = true): TKSeparator
    {
        return $this->create($name, false, fn (PrimitiveFactory $factory) => $factory->mintSeparator($this, $name, $horizontal));
    }

    public function spinner(string $name): TKSpinner
    {
        return $this->create($name, false, fn (PrimitiveFactory $factory) => $factory->mintSpinner($this, $name));
    }

    public function progressBar(string $name, ?float $fraction = 0.0): TKProgressBar
    {
        return $this->create($name, false, fn (PrimitiveFactory $factory) => $factory->mintProgressBar($this, $name, $fraction));
    }

    public function textInput(string $name, string $value = '', ?string $placeholder = null, bool $secret = false): TKTextInput
    {
        return $this->create($name, false, fn (PrimitiveFactory $factory) => $factory->mintTextInput($this, $name, $value, $placeholder, $secret));
    }

    public function textArea(string $name, string $value = ''): TKTextArea
    {
        return $this->create($name, false, fn (PrimitiveFactory $factory) => $factory->mintTextArea($this, $name, $value));
    }

    public function checkbox(string $name, string $label, bool $checked = false): TKCheckbox
    {
        return $this->create($name, false, fn (PrimitiveFactory $factory) => $factory->mintCheckbox($this, $name, $label, $checked));
    }

    public function toggle(string $name, bool $on = false): TKToggle
    {
        return $this->create($name, false, fn (PrimitiveFactory $factory) => $factory->mintToggle($this, $name, $on));
    }

    public function toggleButton(string $name, string $label, bool $pressed = false): TKToggleButton
    {
        return $this->create($name, false, fn (PrimitiveFactory $factory) => $factory->mintToggleButton($this, $name, $label, $pressed));
    }

    public function slider(string $name, float $min, float $max, float $value): TKSlider
    {
        return $this->create($name, false, fn (PrimitiveFactory $factory) => $factory->mintSlider($this, $name, $min, $max, $value));
    }

    public function dropdown(string $name, array $options, int $selected = 0): TKDropdown
    {
        return $this->create($name, false, fn (PrimitiveFactory $factory) => $factory->mintDropdown($this, $name, $options, $selected));
    }

    public function datepicker(string $name, ?DateTimeImmutable $date = null): TKDatepicker
    {
        return $this->create($name, false, fn (PrimitiveFactory $factory) => $factory->mintDatepicker($this, $name, $date));
    }

    public function table(string $name, array $columns, array $rows = []): TKTable
    {
        return $this->create($name, false, fn (PrimitiveFactory $factory) => $factory->mintTable($this, $name, $columns, $rows));
    }

    public function video(string $name, ?string $file = null): TKVideo
    {
        return $this->create($name, false, fn (PrimitiveFactory $factory) => $factory->mintVideo($this, $name, $file));
    }

    public function column(string $name, int $spacing = 0, int $padding = 0): TKColumn
    {
        return $this->create($name, true, fn (PrimitiveFactory $factory) => $factory->mintColumn($this, $name, $spacing, $padding));
    }

    public function row(string $name, int $spacing = 0, int $padding = 0): TKRow
    {
        return $this->create($name, true, fn (PrimitiveFactory $factory) => $factory->mintRow($this, $name, $spacing, $padding));
    }

    public function grid(string $name, int $spacing = 0, int $padding = 0): TKGrid
    {
        return $this->create($name, true, fn (PrimitiveFactory $factory) => $factory->mintGrid($this, $name, $spacing, $padding));
    }

    public function fixed(string $name): TKFixed
    {
        return $this->create($name, true, fn (PrimitiveFactory $factory) => $factory->mintFixed($this, $name));
    }

    public function scrollView(string $name): TKScrollView
    {
        return $this->create($name, false, fn (PrimitiveFactory $factory) => $factory->mintScrollView($this, $name));
    }

    public function view(string $path): ?Child
    {
        [$head, $rest] = array_pad(explode('.', $path, 2), 2, null);
        $child = $this->children[$head] ?? null;

        if (is_null($rest) || is_null($child)) {
            return $child;
        }

        return $child instanceof PrimitiveContract ? $child->view($rest) : null;
    }

    public function children(): array
    {
        return array_values($this->children);
    }

    /**
     * The placement the child being minted takes: the pending cell/frame, else the
     * container's default. For factories, which call it once per mint.
     *
     * @return Placement
     * @throws WindowException When the container needs at() and none is pending.
     */
    public function takePlacement(): Placement
    {
        $placement = $this->pending ?? $this->defaultPlacement();
        $this->pending = null;

        return $placement;
    }

    /**
     * Drop a removed child. Called by the child's remove().
     *
     * @param Child $child
     * @return void
     * @throws WindowException When $child is not a child here or is not removed.
     */
    public function forgetChild(Child $child): void
    {
        $this->own($child);
        if (! $child->isRemoved()) {
            throw new WindowException("'{$child->path()}' is not removed; call its remove().");
        }
        unset($this->children[$child->name()]);
    }

    /**
     * Terminal: remove every child (deepest first), then this container.
     *
     * @return void
     * @throws WindowException When already removed.
     */
    public function remove(): void
    {
        $this->live();
        foreach (array_reverse($this->children) as $child) {
            $child->remove();
        }
        parent::remove();
    }

    /**
     * Admit, mint, adopt. A refusal or a factory exception clears the pending at().
     *
     * @template T of Child
     * @param string $name
     * @param bool $group whether the kind is a column, row, grid or fixed
     * @param Closure(PrimitiveFactory): T $mint
     * @return T
     * @throws WindowException
     */
    protected function create(string $name, bool $group, Closure $mint): Child
    {
        try {
            $this->admit($name, $group);
            $child = $mint($this->window->factory());
        } finally {
            $this->pending = null;
        }

        return $this->adopt($child);
    }

    /**
     * Refuse before the factory mints: removed container, bad name, sibling collision,
     * then the container kind's own rule.
     *
     * @param string $name
     * @param bool $group
     * @return void
     * @throws WindowException
     */
    protected function admit(string $name, bool $group): void
    {
        $this->live();
        PrimitiveRegistry::guardName($name);
        if (isset($this->children[$name])) {
            throw new WindowException("'{$this->path()}' already has a child named '{$name}'.");
        }

        $this->admitChild($name, $group, $this->pending ?? $this->defaultPlacement());
    }

    /**
     * The container kind's own refusal (grid cell taken, scroll view already full, …).
     *
     * @param string $name
     * @param bool $group
     * @param Placement $placement the placement the child would take
     * @return void
     * @throws WindowException
     */
    protected function admitChild(string $name, bool $group, Placement $placement): void {}

    /**
     * Register a freshly minted child and hand it to the engine.
     *
     * @template T of Child
     * @param T $child
     * @return T
     */
    protected function adopt(Child $child): Child
    {
        $this->window->registry()->register($child);
        $this->children[$child->name()] = $child;
        $this->insertNative($child);

        return $child;
    }

    /**
     * @param Child $child
     * @return void
     * @throws WindowException When $child is not a child of this container.
     */
    protected function own(Child $child): void
    {
        if (($this->children[$child->name()] ?? null) !== $child) {
            throw new WindowException("'{$child->path()}' is not a child of '{$this->path()}'.");
        }
    }

    /**
     * Column, row and scroll view: Placement::next(); grid and fixed: throw, at() is required.
     *
     * @return Placement
     * @throws WindowException
     */
    abstract protected function defaultPlacement(): Placement;

    /**
     * Put the child's native into this container's native at its placement.
     *
     * @param Child $child
     * @return void
     */
    abstract protected function insertNative(Child $child): void;
}
