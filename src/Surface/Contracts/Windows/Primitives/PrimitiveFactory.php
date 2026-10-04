<?php

namespace Surface\Contracts\Windows\Primitives;

use DateTimeImmutable;
use Surface\Contracts\Windows\WindowException;

/**
 * The driver's minter: one mint per kind, building the concrete primitive and its native.
 * Containers call it after admitting the name and placement; $host is the container the
 * primitive is created in (null = the window's content container). A kind the engine
 * lacks throws WindowException("TK<Kind> is not available on <kit>.").
 */
interface PrimitiveFactory
{
    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param string $text
     * @return TKLabel
     */
    public function mintLabel(TKPrimitiveGroup $host, string $name, string $text): TKLabel;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param string $label
     * @return TKButton
     */
    public function mintButton(TKPrimitiveGroup $host, string $name, string $label): TKButton;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param string|null $file
     * @return TKImage
     */
    public function mintImage(TKPrimitiveGroup $host, string $name, ?string $file): TKImage;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @return TKCanvas
     */
    public function mintCanvas(TKPrimitiveGroup $host, string $name): TKCanvas;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param bool $horizontal
     * @return TKSeparator
     */
    public function mintSeparator(TKPrimitiveGroup $host, string $name, bool $horizontal): TKSeparator;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @return TKSpinner
     * @throws WindowException When the engine has no spinner.
     */
    public function mintSpinner(TKPrimitiveGroup $host, string $name): TKSpinner;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param float|null $fraction
     * @return TKProgressBar
     */
    public function mintProgressBar(TKPrimitiveGroup $host, string $name, ?float $fraction): TKProgressBar;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param string $value
     * @param string|null $placeholder
     * @param bool $secret
     * @return TKTextInput
     */
    public function mintTextInput(TKPrimitiveGroup $host, string $name, string $value, ?string $placeholder, bool $secret): TKTextInput;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param string $value
     * @return TKTextArea
     */
    public function mintTextArea(TKPrimitiveGroup $host, string $name, string $value): TKTextArea;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param string $label
     * @param bool $checked
     * @return TKCheckbox
     */
    public function mintCheckbox(TKPrimitiveGroup $host, string $name, string $label, bool $checked): TKCheckbox;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param bool $on
     * @return TKToggle
     * @throws WindowException When the engine has no switch.
     */
    public function mintToggle(TKPrimitiveGroup $host, string $name, bool $on): TKToggle;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param string $label
     * @param bool $pressed
     * @return TKToggleButton
     */
    public function mintToggleButton(TKPrimitiveGroup $host, string $name, string $label, bool $pressed): TKToggleButton;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param float $min
     * @param float $max
     * @param float $value
     * @return TKSlider
     */
    public function mintSlider(TKPrimitiveGroup $host, string $name, float $min, float $max, float $value): TKSlider;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param list<string> $options
     * @param int $selected
     * @return TKDropdown
     */
    public function mintDropdown(TKPrimitiveGroup $host, string $name, array $options, int $selected): TKDropdown;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param DateTimeImmutable|null $date
     * @return TKDatepicker
     */
    public function mintDatepicker(TKPrimitiveGroup $host, string $name, ?DateTimeImmutable $date): TKDatepicker;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param list<TableColumn> $columns
     * @param list<array<string, scalar|null>> $rows
     * @return TKTable
     */
    public function mintTable(TKPrimitiveGroup $host, string $name, array $columns, array $rows): TKTable;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @param string|null $file
     * @return TKVideo
     * @throws WindowException When the engine has no media runtime.
     */
    public function mintVideo(TKPrimitiveGroup $host, string $name, ?string $file): TKVideo;

    /**
     * @param TKPrimitiveGroup|null $host null = the window's content container
     * @param string $name
     * @param int $spacing
     * @param int $padding
     * @return TKColumn
     */
    public function mintColumn(?TKPrimitiveGroup $host, string $name, int $spacing, int $padding): TKColumn;

    /**
     * @param TKPrimitiveGroup|null $host null = the window's content container
     * @param string $name
     * @param int $spacing
     * @param int $padding
     * @return TKRow
     */
    public function mintRow(?TKPrimitiveGroup $host, string $name, int $spacing, int $padding): TKRow;

    /**
     * @param TKPrimitiveGroup|null $host null = the window's content container
     * @param string $name
     * @param int $spacing
     * @param int $padding
     * @return TKGrid
     */
    public function mintGrid(?TKPrimitiveGroup $host, string $name, int $spacing, int $padding): TKGrid;

    /**
     * @param TKPrimitiveGroup|null $host null = the window's content container
     * @param string $name
     * @return TKFixed
     */
    public function mintFixed(?TKPrimitiveGroup $host, string $name): TKFixed;

    /**
     * @param TKPrimitiveGroup $host
     * @param string $name
     * @return TKScrollView
     */
    public function mintScrollView(TKPrimitiveGroup $host, string $name): TKScrollView;
}
