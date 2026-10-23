<?php

declare(strict_types=1);

use Surface\Contracts\Windows\Primitives\Align;
use Surface\Contracts\Windows\Primitives\ImageScaling;
use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\PrimitiveFactory;
use Surface\Contracts\Windows\Primitives\PrimitiveRegistry;
use Surface\Contracts\Windows\Primitives\TableColumn;
use Surface\Contracts\Windows\Primitives\TKButton;
use Surface\Contracts\Windows\Primitives\TKCheckbox;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKDatepicker;
use Surface\Contracts\Windows\Primitives\TKDropdown;
use Surface\Contracts\Windows\Primitives\TKFixed;
use Surface\Contracts\Windows\Primitives\TKGrid;
use Surface\Contracts\Windows\Primitives\TKImage;
use Surface\Contracts\Windows\Primitives\TKLabel;
use Surface\Contracts\Windows\Primitives\TKLink;
use Surface\Contracts\Windows\Primitives\TKPrimitive;
use Surface\Contracts\Windows\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Windows\Primitives\TKProgressBar;
use Surface\Contracts\Windows\Primitives\TKRow;
use Surface\Contracts\Windows\Primitives\TKScrollView;
use Surface\Contracts\Windows\Primitives\TKSeparator;
use Surface\Contracts\Windows\Primitives\TKSlider;
use Surface\Contracts\Windows\Primitives\TKSpinner;
use Surface\Contracts\Windows\Primitives\TKSplitView;
use Surface\Contracts\Windows\Primitives\TKTable;
use Surface\Contracts\Windows\Primitives\TKTabView;
use Surface\Contracts\Windows\Primitives\TKTree;
use Surface\Contracts\Windows\Primitives\TKTextArea;
use Surface\Contracts\Windows\Primitives\TKTextInput;
use Surface\Contracts\Windows\Primitives\TKToggle;
use Surface\Contracts\Windows\Primitives\TKToggleButton;
use Surface\Contracts\Windows\Primitives\TKVideo;
use Surface\Contracts\Windows\ToolkitWindow;

$methods = [
    TKPrimitive::class => ['name', 'path', 'uuid', 'window', 'parent', 'placement', 'isRemoved', 'remove', 'setVisible', 'isVisible', 'show', 'hide', 'setEnabled', 'isEnabled', 'enable', 'disable', 'size', 'watchSize', 'isWatchingSize', 'setContextMenu', 'contextMenu', 'setBackground', 'fill', 'align', 'minSize', 'moveBefore', 'moveAfter', 'moveTo'],
    TKPrimitiveGroup::class => ['label', 'link', 'button', 'image', 'separator', 'spinner', 'progressBar', 'textInput', 'textArea', 'checkbox', 'toggle', 'toggleButton', 'slider', 'dropdown', 'datepicker', 'table', 'tree', 'video', 'column', 'row', 'grid', 'fixed', 'scrollView', 'split', 'tabs', 'view', 'children', 'takePlacement'],
    TKColumn::class => ['spacing', 'setSpacing'],
    TKRow::class => ['spacing', 'setSpacing'],
    TKGrid::class => ['at', 'spacing', 'setSpacing'],
    TKFixed::class => ['at', 'move', 'resize', 'frameOf'],
    TKScrollView::class => ['setScrollbars', 'content'],
    TKSplitView::class => ['isHorizontal', 'positions', 'setPositions'],
    TKTabView::class => ['addPage', 'pages', 'setTitle', 'title', 'selected', 'select'],
    TKLabel::class => ['text', 'setText', 'setWrap', 'setAlignment', 'setFont', 'setTextColor'],
    TKLink::class => ['text', 'setText', 'url', 'setUrl', 'setAlignment', 'setFont'],
    TKButton::class => ['label', 'setLabel', 'setFont', 'setTextColor'],
    TKImage::class => ['file', 'setFile', 'setScaling'],
    TKSeparator::class => ['isHorizontal'],
    TKSpinner::class => ['start', 'stop', 'isSpinning'],
    TKProgressBar::class => ['fraction', 'setFraction'],
    TKTextInput::class => ['value', 'setValue', 'placeholder', 'setPlaceholder', 'isSecret', 'setFont', 'setTextColor'],
    TKTextArea::class => ['value', 'setValue', 'setFont', 'setTextColor', 'setReadOnly', 'isReadOnly', 'setWrap', 'wraps', 'setLineBackgrounds', 'setRangeStyles', 'lineCount', 'caretLine', 'selectedLines', 'selectLines', 'scrollPosition', 'scrollTo', 'scrollToLine', 'watchScroll', 'isWatchingScroll'],
    TKCheckbox::class => ['label', 'setLabel', 'isChecked', 'setChecked'],
    TKToggle::class => ['isOn', 'setOn'],
    TKToggleButton::class => ['label', 'setLabel', 'isPressed', 'setPressed'],
    TKSlider::class => ['value', 'setValue', 'min', 'max', 'setRange'],
    TKDropdown::class => ['options', 'setOptions', 'selected', 'select', 'selectedOption'],
    TKDatepicker::class => ['date', 'setDate'],
    TKTable::class => ['columns', 'rows', 'setRows', 'appendRow', 'clearRows', 'selectedRow', 'selectRow'],
    TKTree::class => ['columns', 'setNodes', 'nodes', 'expand', 'isExpanded', 'selectedNode', 'selectNode'],
    TKVideo::class => ['file', 'setFile', 'play', 'pause', 'seek', 'position', 'duration', 'isPlaying', 'setMuted', 'isMuted', 'setLoop', 'isLooping'],
    PrimitiveFactory::class => ['mintLabel', 'mintLink', 'mintButton', 'mintImage', 'mintSeparator', 'mintSpinner', 'mintProgressBar', 'mintTextInput', 'mintTextArea', 'mintCheckbox', 'mintToggle', 'mintToggleButton', 'mintSlider', 'mintDropdown', 'mintDatepicker', 'mintTable', 'mintTree', 'mintVideo', 'mintColumn', 'mintRow', 'mintGrid', 'mintFixed', 'mintScrollView', 'mintSplit', 'mintTabs'],
    ToolkitWindow::class => ['column', 'row', 'grid', 'fixed', 'content', 'view', 'uuid', 'size', 'registry', 'factory', 'forgetContent'],
];

$pairs = [];
foreach ($methods as $interface => $names) {
    foreach ($names as $method) {
        $pairs[substr(strrchr($interface, '\\'), 1).'::'.$method] = [$interface, $method];
    }
}

it('declares the method on the interface', function (string $interface, string $method): void {
    expect(interface_exists($interface))->toBeTrue()
        ->and(method_exists($interface, $method))->toBeTrue();
})->with($pairs);

it('nests every container and leaf under the primitive contracts', function (): void {
    foreach ([TKColumn::class, TKRow::class, TKGrid::class, TKFixed::class, TKScrollView::class, TKSplitView::class] as $group) {
        expect(is_subclass_of($group, TKPrimitiveGroup::class))->toBeTrue();
    }
    foreach ([TKLabel::class, TKLink::class, TKButton::class, TKImage::class, TKSeparator::class, TKSpinner::class, TKProgressBar::class, TKTextInput::class, TKTextArea::class, TKCheckbox::class, TKToggle::class, TKToggleButton::class, TKSlider::class, TKDropdown::class, TKDatepicker::class, TKTable::class, TKTree::class, TKVideo::class, TKTabView::class] as $leaf) {
        expect(is_subclass_of($leaf, TKPrimitive::class))->toBeTrue()
            ->and(is_subclass_of($leaf, TKPrimitiveGroup::class))->toBeFalse();
    }
});

it('ships the placement value and the registry with the contracts', function (): void {
    expect(class_exists(Placement::class))->toBeTrue()
        ->and(class_exists(PrimitiveRegistry::class))->toBeTrue()
        ->and(Placement::cell(1, 2, 1, 3)->columnSpan)->toBe(3);
});

it('carries a table column id and label, and the align and scaling cases', function (): void {
    $column = new TableColumn('title', 'Title');

    expect($column->id)->toBe('title')
        ->and($column->label)->toBe('Title')
        ->and(array_map(fn (Align $a): string => $a->value, Align::cases()))->toBe(['start', 'center', 'end', 'fill'])
        ->and(array_map(fn (ImageScaling $s): string => $s->value, ImageScaling::cases()))->toBe(['fit', 'fill', 'center', 'stretch']);
});
