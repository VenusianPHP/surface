<?php

namespace Surface\Contracts\Windows\Primitives;

use DateTimeImmutable;
use Surface\Contracts\Windows\WindowException;

/**
 * A container: the only place primitives are created, and the root of relative lookups.
 * Every creation method throws WindowException for a name with a dot, an empty name,
 * a name a sibling holds, or a kind the engine lacks — before any native exists.
 */
interface TKPrimitiveGroup extends TKPrimitive
{
    /**
     * @param string $name
     * @param string $text
     * @return TKLabel
     */
    public function label(string $name, string $text): TKLabel;

    /**
     * @param string $name
     * @param string $label
     * @return TKButton
     */
    public function button(string $name, string $label): TKButton;

    /**
     * @param string $name
     * @param string|null $file image file
     * @return TKImage
     */
    public function image(string $name, ?string $file = null): TKImage;

    /**
     * @param string $name
     * @param bool $horizontal
     * @return TKSeparator
     */
    public function separator(string $name, bool $horizontal = true): TKSeparator;

    /**
     * @param string $name
     * @return TKSpinner
     */
    public function spinner(string $name): TKSpinner;

    /**
     * @param string $name
     * @param float|null $fraction 0..1, null = indeterminate
     * @return TKProgressBar
     */
    public function progressBar(string $name, ?float $fraction = 0.0): TKProgressBar;

    /**
     * @param string $name
     * @param string $value
     * @param string|null $placeholder
     * @param bool $secret masked entry
     * @return TKTextInput
     */
    public function textInput(string $name, string $value = '', ?string $placeholder = null, bool $secret = false): TKTextInput;

    /**
     * @param string $name
     * @param string $value
     * @return TKTextArea
     */
    public function textArea(string $name, string $value = ''): TKTextArea;

    /**
     * @param string $name
     * @param string $label
     * @param bool $checked
     * @return TKCheckbox
     */
    public function checkbox(string $name, string $label, bool $checked = false): TKCheckbox;

    /**
     * @param string $name
     * @param bool $on
     * @return TKToggle
     */
    public function toggle(string $name, bool $on = false): TKToggle;

    /**
     * @param string $name
     * @param string $label
     * @param bool $pressed
     * @return TKToggleButton
     */
    public function toggleButton(string $name, string $label, bool $pressed = false): TKToggleButton;

    /**
     * @param string $name
     * @param float $min
     * @param float $max
     * @param float $value
     * @return TKSlider
     */
    public function slider(string $name, float $min, float $max, float $value): TKSlider;

    /**
     * @param string $name
     * @param list<string> $options
     * @param int $selected
     * @return TKDropdown
     */
    public function dropdown(string $name, array $options, int $selected = 0): TKDropdown;

    /**
     * @param string $name
     * @param DateTimeImmutable|null $date
     * @return TKDatepicker
     */
    public function datepicker(string $name, ?DateTimeImmutable $date = null): TKDatepicker;

    /**
     * @param string $name
     * @param list<TableColumn> $columns
     * @param list<array<string, scalar|null>> $rows keyed by column id
     * @return TKTable
     */
    public function table(string $name, array $columns, array $rows = []): TKTable;

    /**
     * @param string $name
     * @param string|null $file media file
     * @return TKVideo
     * @throws WindowException When the engine has no media runtime.
     */
    public function video(string $name, ?string $file = null): TKVideo;

    /**
     * @param string $name
     * @param int $spacing between children
     * @param int $padding around the children
     * @return TKColumn
     */
    public function column(string $name, int $spacing = 0, int $padding = 0): TKColumn;

    /**
     * @param string $name
     * @param int $spacing between children
     * @param int $padding around the children
     * @return TKRow
     */
    public function row(string $name, int $spacing = 0, int $padding = 0): TKRow;

    /**
     * @param string $name
     * @param int $spacing between cells
     * @param int $padding around the cells
     * @return TKGrid
     */
    public function grid(string $name, int $spacing = 0, int $padding = 0): TKGrid;

    /**
     * @param string $name
     * @return TKFixed
     */
    public function fixed(string $name): TKFixed;

    /**
     * @param string $name
     * @return TKScrollView
     */
    public function scrollView(string $name): TKScrollView;

    /**
     * Look a descendant up by dotted path relative to this container.
     * @param string $path
     * @return TKPrimitive|null
     */
    public function view(string $path): ?TKPrimitive;

    /**
     * The direct children, in creation order (column/row: display order).
     * @return list<TKPrimitive>
     */
    public function children(): array;

    /**
     * The placement the child being minted takes: the pending at() cell/frame, else the
     * container's default. For factories, once per mint.
     *
     * @return Placement
     * @throws WindowException When the container needs at() and none is pending.
     */
    public function takePlacement(): Placement;
}
