<?php

declare(strict_types=1);

namespace Venusian\Surface\Tests\Fixtures;

use DateTimeImmutable;
use Surface\Contracts\Windows\Primitives\Align;
use Surface\Contracts\Windows\Primitives\PrimitiveFactory;
use Surface\Contracts\Windows\Primitives\TKButton as ButtonContract;
use Surface\Contracts\Windows\Primitives\TKCheckbox;
use Surface\Contracts\Windows\Primitives\TKColumn as ColumnContract;
use Surface\Contracts\Windows\Primitives\TKDatepicker;
use Surface\Contracts\Windows\Primitives\TKDropdown;
use Surface\Contracts\Windows\Primitives\TKFixed as FixedContract;
use Surface\Contracts\Windows\Primitives\TKGrid as GridContract;
use Surface\Contracts\Windows\Primitives\TKCanvas;
use Surface\Contracts\Windows\Primitives\TKImage;
use Surface\Contracts\Windows\Primitives\TKLabel as LabelContract;
use Surface\Contracts\Windows\Primitives\TKPrimitive as PrimitiveContract;
use Surface\Contracts\Windows\Primitives\TKPrimitiveGroup as GroupContract;
use Surface\Contracts\Windows\Primitives\TKProgressBar;
use Surface\Contracts\Windows\Primitives\TKRow as RowContract;
use Surface\Contracts\Windows\Primitives\TKScrollView as ScrollContract;
use Surface\Contracts\Windows\Primitives\TKSeparator;
use Surface\Contracts\Windows\Primitives\TKSlider;
use Surface\Contracts\Windows\Primitives\TKSpinner;
use Surface\Contracts\Windows\Primitives\TKTable;
use Surface\Contracts\Windows\Primitives\TKTextArea;
use Surface\Contracts\Windows\Primitives\TKTextInput;
use Surface\Contracts\Windows\Primitives\TKToggle;
use Surface\Contracts\Windows\Primitives\TKToggleButton;
use Surface\Contracts\Windows\Primitives\TKVideo;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\TextAlignment;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\WindowException;
use Surface\NutsAndBolts\Color;
use Surface\Windows\Primitives\HostsPrimitives;
use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\ImageScaling;
use Surface\Windows\Primitives\TKButton;
use Surface\Windows\Primitives\TKCheckbox as CheckboxAbstract;
use Surface\Windows\Primitives\TKDatepicker as DatepickerAbstract;
use Surface\Windows\Primitives\TKDropdown as DropdownAbstract;
use Surface\Windows\Primitives\TKCanvas as CanvasAbstract;
use Surface\Windows\Primitives\TKImage as ImageAbstract;
use Surface\Windows\Primitives\TKProgressBar as ProgressBarAbstract;
use Surface\Windows\Primitives\TKSeparator as SeparatorAbstract;
use Surface\Windows\Primitives\TKSlider as SliderAbstract;
use Surface\Windows\Primitives\TKSpinner as SpinnerAbstract;
use Surface\Windows\Primitives\TKTable as TableAbstract;
use Surface\Windows\Primitives\TKTextArea as TextAreaAbstract;
use Surface\Windows\Primitives\TKTextInput as TextInputAbstract;
use Surface\Windows\Primitives\TKToggle as ToggleAbstract;
use Surface\Windows\Primitives\TKToggleButton as ToggleButtonAbstract;
use Surface\Windows\Primitives\TKVideo as VideoAbstract;
use Surface\Windows\Primitives\TKColumn;
use Surface\Windows\Primitives\TKFixed;
use Surface\Windows\Primitives\TKGrid;
use Surface\Windows\Primitives\TKLabel;
use Surface\Windows\Primitives\TKRow;
use Surface\Windows\Primitives\TKScrollView;

/** A toolkit window with no toolkit: hosts primitives through the fake factory. */
final class FakeHost implements ToolkitWindow
{
    use HostsPrimitives;

    /** @var list<string> paths of the content containers mounted */
    public array $mounted = [];

    public readonly FakePrimitiveFactory $minter;

    private bool $open = true;

    public function __construct(private readonly string $name)
    {
        $this->minter = new FakePrimitiveFactory($this);
    }

    public function factory(): PrimitiveFactory { return $this->minter; }

    protected function mountContent(GroupContract $content): void { $this->mounted[] = $content->path(); }

    public function size(): array { return [640, 400]; }

    public function name(): string { return $this->name; }

    public function close(): void
    {
        $this->removeContent();
        $this->open = false;
    }

    public function isOpen(): bool { return $this->open; }

    public function title(): string { return $this->name; }

    public function present(): static { return $this; }

    public function isToggled(string $item): bool { return false; }

    public function setTitle(string $title): static { return $this; }

    public function setMenuBar(string $profile): static { return $this; }

    public function setToggle(string $item, bool $on): static { return $this; }
}

/** Mints the fake concretes; every kind the fake engine lacks throws, as a driver's would. */
final class FakePrimitiveFactory implements PrimitiveFactory
{
    /** @var list<string> names minted, in order */
    public array $minted = [];

    /** @var list<string> kinds this fake engine lacks, e.g. 'Toggle' */
    public array $lacking = [];

    public function __construct(private readonly ToolkitWindow $window) {}

    /** @return array{ToolkitWindow, GroupContract, Placement} */
    private function slot(GroupContract $host, string $name, string $kind): array
    {
        if (in_array($kind, $this->lacking, true)) {
            throw new WindowException("TK{$kind} is not available on fake.");
        }
        $this->minted[] = $name;

        return [$this->window, $host, $host->takePlacement()];
    }

    public function mintLabel(GroupContract $host, string $name, string $text): LabelContract { return new FakeLabel($name, ...[...$this->slot($host, $name, 'Label'), $text]); }

    public function mintButton(GroupContract $host, string $name, string $label): ButtonContract { return new FakeButton($name, ...[...$this->slot($host, $name, 'Button'), $label]); }

    public function mintImage(GroupContract $host, string $name, ?string $file): TKImage { return new FakeImage($name, ...[...$this->slot($host, $name, 'Image'), $file]); }

    public function mintCanvas(GroupContract $host, string $name): TKCanvas { return new FakeCanvas($name, ...$this->slot($host, $name, 'Canvas')); }

    public function mintSeparator(GroupContract $host, string $name, bool $horizontal): TKSeparator { return new FakeSeparator($name, ...[...$this->slot($host, $name, 'Separator'), $horizontal]); }

    public function mintSpinner(GroupContract $host, string $name): TKSpinner { return new FakeSpinner($name, ...$this->slot($host, $name, 'Spinner')); }

    public function mintProgressBar(GroupContract $host, string $name, ?float $fraction): TKProgressBar { return new FakeProgressBar($name, ...[...$this->slot($host, $name, 'ProgressBar'), $fraction]); }

    public function mintTextInput(GroupContract $host, string $name, string $value, ?string $placeholder, bool $secret): TKTextInput { return new FakeTextInput($name, ...[...$this->slot($host, $name, 'TextInput'), $value, $placeholder, $secret]); }

    public function mintTextArea(GroupContract $host, string $name, string $value): TKTextArea { return new FakeTextArea($name, ...[...$this->slot($host, $name, 'TextArea'), $value]); }

    public function mintCheckbox(GroupContract $host, string $name, string $label, bool $checked): TKCheckbox { return new FakeCheckbox($name, ...[...$this->slot($host, $name, 'Checkbox'), $label, $checked]); }

    public function mintToggle(GroupContract $host, string $name, bool $on): TKToggle { return new FakeToggle($name, ...[...$this->slot($host, $name, 'Toggle'), $on]); }

    public function mintToggleButton(GroupContract $host, string $name, string $label, bool $pressed): TKToggleButton { return new FakeToggleButton($name, ...[...$this->slot($host, $name, 'ToggleButton'), $label, $pressed]); }

    public function mintSlider(GroupContract $host, string $name, float $min, float $max, float $value): TKSlider { return new FakeSlider($name, ...[...$this->slot($host, $name, 'Slider'), $min, $max, $value]); }

    public function mintDropdown(GroupContract $host, string $name, array $options, int $selected): TKDropdown { return new FakeDropdown($name, ...[...$this->slot($host, $name, 'Dropdown'), $options, $selected]); }

    public function mintDatepicker(GroupContract $host, string $name, ?DateTimeImmutable $date): TKDatepicker { return new FakeDatepicker($name, ...[...$this->slot($host, $name, 'Datepicker'), $date]); }

    public function mintTable(GroupContract $host, string $name, array $columns, array $rows): TKTable { return new FakeTable($name, ...[...$this->slot($host, $name, 'Table'), $columns, $rows]); }

    public function mintVideo(GroupContract $host, string $name, ?string $file): TKVideo { return new FakeVideo($name, ...[...$this->slot($host, $name, 'Video'), $file]); }

    public function mintColumn(?GroupContract $host, string $name, int $spacing, int $padding): ColumnContract { return new FakeColumn($name, ...[...$this->groupSlot($host, $name, 'Column'), $spacing, $padding]); }

    public function mintRow(?GroupContract $host, string $name, int $spacing, int $padding): RowContract { return new FakeRow($name, ...[...$this->groupSlot($host, $name, 'Row'), $spacing, $padding]); }

    public function mintGrid(?GroupContract $host, string $name, int $spacing, int $padding): GridContract { return new FakeGrid($name, ...[...$this->groupSlot($host, $name, 'Grid'), $spacing, $padding]); }

    public function mintFixed(?GroupContract $host, string $name): FixedContract { return new FakeFixed($name, ...$this->groupSlot($host, $name, 'Fixed')); }

    public function mintScrollView(GroupContract $host, string $name): ScrollContract { return new FakeScrollView($name, ...$this->slot($host, $name, 'ScrollView')); }

    /** @return array{ToolkitWindow, ?GroupContract, Placement} */
    private function groupSlot(?GroupContract $host, string $name, string $kind): array
    {
        if (is_null($host)) {
            if (in_array($kind, $this->lacking, true)) {
                throw new WindowException("TK{$kind} is not available on fake.");
            }
            $this->minted[] = $name;

            return [$this->window, null, Placement::next()];
        }

        return $this->slot($host, $name, $kind);
    }
}

/** The base hooks every fake native shares: each appends what the engine was asked to $log. */
trait FakeNative
{
    /** @var list<string> */
    public array $log = [];

    protected function destroyNative(): void { $this->log[] = 'destroy'; }

    protected function applyVisible(bool $visible): void { $this->log[] = 'visible:'.(int) $visible; }

    protected function applyEnabled(bool $enabled): void { $this->log[] = 'enabled:'.(int) $enabled; }

    protected function applyBackground(?Color $color): void { $this->log[] = 'background:'.($color?->toHex() ?? 'none'); }

    protected function applyFill(bool $horizontal, bool $vertical): void { $this->log[] = 'fill:'.(int) $horizontal.','.(int) $vertical; }

    protected function applyAlign(Align $horizontal, Align $vertical): void { $this->log[] = "align:{$horizontal->value},{$vertical->value}"; }

    protected function applyMinSize(int $width, int $height): void { $this->log[] = "min:{$width},{$height}"; }

    protected function applyWatchSize(bool $on): void { $this->log[] = 'watch:'.(int) $on; }

    protected function nativeSize(): array { return [10, 10]; }
}

/** Container hooks shared by every fake group. */
trait FakeGroupNative
{
    use FakeNative;

    protected function insertNative(PrimitiveContract $child): void { $this->log[] = 'insert:'.$child->path(); }
}

/** Column/row hooks. */
trait FakeStackNative
{
    use FakeGroupNative;

    protected function applySpacing(int $spacing): void { $this->log[] = "spacing:{$spacing}"; }

    protected function applyOrder(PrimitiveContract $child, int $index): void { $this->log[] = "order:{$child->name()}:{$index}"; }
}

final class FakeColumn extends TKColumn
{
    use FakeStackNative;
}

final class FakeRow extends TKRow
{
    use FakeStackNative;
}

final class FakeGrid extends TKGrid
{
    use FakeGroupNative;

    protected function applySpacing(int $spacing): void { $this->log[] = "spacing:{$spacing}"; }
}

final class FakeFixed extends TKFixed
{
    use FakeGroupNative;

    protected function applyMove(PrimitiveContract $child, int $x, int $y): void { $this->log[] = "move:{$child->name()}:{$x},{$y}"; }

    protected function applyResize(PrimitiveContract $child, int $width, int $height): void { $this->log[] = "resize:{$child->name()}:{$width},{$height}"; }
}

final class FakeScrollView extends TKScrollView
{
    use FakeGroupNative;

    protected function applyScrollbars(bool $horizontal, bool $vertical): void { $this->log[] = 'scrollbars:'.(int) $horizontal.','.(int) $vertical; }
}

final class FakeLabel extends TKLabel
{
    use FakeNative;

    protected function applyText(string $text): void { $this->log[] = "text:{$text}"; }

    protected function applyWrap(bool $wrap): void { $this->log[] = 'wrap:'.(int) $wrap; }

    protected function applyAlignment(TextAlignment $alignment): void { $this->log[] = "alignment:{$alignment->value}"; }

    protected function applyFont(FontSpec $font): void { $this->log[] = "font:{$font->size}"; }

    protected function applyTextColor(?Color $color): void { $this->log[] = 'color:'.($color?->toHex() ?? 'none'); }
}

final class FakeButton extends TKButton
{
    use FakeNative;

    protected function applyLabel(string $label): void { $this->log[] = "label:{$label}"; }

    protected function applyFont(FontSpec $font): void { $this->log[] = "font:{$font->size}"; }

    protected function applyTextColor(?Color $color): void { $this->log[] = 'color:'.($color?->toHex() ?? 'none'); }
}

final class FakeImage extends ImageAbstract
{
    use FakeNative;

    protected function applyFile(?string $file): void { $this->log[] = 'file:'.($file ?? 'none'); }

    protected function applyScaling(ImageScaling $scaling): void { $this->log[] = "scaling:{$scaling->value}"; }
}

final class FakeCanvas extends CanvasAbstract
{
    use FakeNative;

    /** @var array{int, int} What the toolkit says the view measures; [0, 0] is a view not laid out yet. */
    public array $measures = [40, 30];

    public float $scale = 2.0;

    /** @var list<array{string, int, int}> Every image handed to the toolkit. */
    public array $pixels = [];

    protected function nativeSize(): array { return $this->measures; }

    protected function nativeScale(): float { return $this->scale; }

    protected function applyPixels(string $rgba8, int $width, int $height): void { $this->pixels[] = [$rgba8, $width, $height]; }
}

final class FakeSeparator extends SeparatorAbstract
{
    use FakeNative;
}

final class FakeSpinner extends SpinnerAbstract
{
    use FakeNative;

    protected function applySpinning(bool $spinning): void { $this->log[] = 'spinning:'.(int) $spinning; }
}

final class FakeProgressBar extends ProgressBarAbstract
{
    use FakeNative;

    protected function applyFraction(?float $fraction): void { $this->log[] = 'fraction:'.($fraction ?? 'none'); }
}

final class FakeTextInput extends TextInputAbstract
{
    use FakeNative;

    protected function applyValue(string $value): void { $this->log[] = "value:{$value}"; }

    protected function applyPlaceholder(?string $placeholder): void { $this->log[] = 'placeholder:'.($placeholder ?? 'none'); }

    protected function applyFont(FontSpec $font): void { $this->log[] = "font:{$font->size}"; }

    protected function applyTextColor(?Color $color): void { $this->log[] = 'color:'.($color?->toHex() ?? 'none'); }
}

final class FakeTextArea extends TextAreaAbstract
{
    use FakeNative;

    protected function applyValue(string $value): void { $this->log[] = "value:{$value}"; }

    protected function applyFont(FontSpec $font): void { $this->log[] = "font:{$font->size}"; }

    protected function applyTextColor(?Color $color): void { $this->log[] = 'color:'.($color?->toHex() ?? 'none'); }
}

final class FakeCheckbox extends CheckboxAbstract
{
    use FakeNative;

    protected function applyLabel(string $label): void { $this->log[] = "label:{$label}"; }

    protected function applyChecked(bool $checked): void { $this->log[] = 'checked:'.(int) $checked; }
}

final class FakeToggle extends ToggleAbstract
{
    use FakeNative;

    protected function applyOn(bool $on): void { $this->log[] = 'on:'.(int) $on; }
}

final class FakeToggleButton extends ToggleButtonAbstract
{
    use FakeNative;

    protected function applyLabel(string $label): void { $this->log[] = "label:{$label}"; }

    protected function applyPressed(bool $pressed): void { $this->log[] = 'pressed:'.(int) $pressed; }
}

final class FakeSlider extends SliderAbstract
{
    use FakeNative;

    protected function applyValue(float $value): void { $this->log[] = "value:{$value}"; }

    protected function applyRange(float $min, float $max): void { $this->log[] = "range:{$min},{$max}"; }
}

final class FakeDropdown extends DropdownAbstract
{
    use FakeNative;

    protected function applyOptions(array $options): void { $this->log[] = 'options:'.implode('|', $options); }

    protected function applySelected(int $index): void { $this->log[] = "selected:{$index}"; }
}

final class FakeDatepicker extends DatepickerAbstract
{
    use FakeNative;

    protected function applyDate(?DateTimeImmutable $date): void { $this->log[] = 'date:'.($date?->format('Y-m-d') ?? 'none'); }
}

final class FakeTable extends TableAbstract
{
    use FakeNative;

    /** @return list<list<string>> the cell grid a driver builds its native from */
    public function engineCells(): array { return $this->cellRows(); }

    protected function applyRows(array $cells): void { $this->log[] = 'rows:'.count($cells); }

    protected function applySelectedRow(?int $row): void { $this->log[] = 'selected:'.($row ?? 'none'); }
}

final class FakeVideo extends VideoAbstract
{
    use FakeNative;

    public float $position = 0.0;

    public ?float $duration = null;

    protected function applyFile(?string $file): void { $this->log[] = 'file:'.($file ?? 'none'); }

    protected function applyPlay(): void { $this->log[] = 'play'; }

    protected function applyPause(): void { $this->log[] = 'pause'; }

    protected function applySeek(float $seconds): void { $this->log[] = "seek:{$seconds}"; }

    protected function applyMuted(bool $muted): void { $this->log[] = 'muted:'.(int) $muted; }

    protected function applyLoop(bool $loop): void { $this->log[] = 'loop:'.(int) $loop; }

    protected function nativePosition(): float { return $this->position; }

    protected function nativeDuration(): ?float { return $this->duration; }
}
