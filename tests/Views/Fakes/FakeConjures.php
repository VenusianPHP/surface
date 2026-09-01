<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Surface\NativeWindows\Enums\Orientation;
use Surface\NativeWindows\Views\Box;
use Surface\NativeWindows\Views\Button;
use Surface\NativeWindows\Views\Checkbox;
use Surface\NativeWindows\Views\Dropdown;
use Surface\NativeWindows\Views\Entry;
use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\Image;
use Surface\NativeWindows\Views\Label;
use Surface\NativeWindows\Views\Password;
use Surface\NativeWindows\Views\Popover;
use Surface\NativeWindows\Views\Progress;
use Surface\NativeWindows\Views\Radio;
use Surface\NativeWindows\Views\Scroll;
use Surface\NativeWindows\Views\Size;
use Surface\NativeWindows\Views\Slider;
use Surface\NativeWindows\Views\Spinner;
use Surface\NativeWindows\Views\Split;
use Surface\NativeWindows\Views\SwitchControl;
use Surface\NativeWindows\Views\Tabs;
use Surface\NativeWindows\Views\Text;

/** nativeCreate* hooks shared by every fake container (Box, Scroll, Pane, Popover). */
trait FakeConjures
{
    protected function nativeCreateLabel(string $nickname, string $text, Frame $frame): Label
    {
        $pointer = $this->log->nextPointer();
        $this->log->record('createLabel', $pointer, $text);

        return new FakeLabel($this->log, $pointer, $nickname, $this, $frame);
    }

    protected function nativeCreateBox(string $nickname, Frame $frame): Box
    {
        $pointer = $this->log->nextPointer();
        $this->log->record('createBox', $pointer);

        return new FakeBox($this->log, $pointer, $nickname, $this, $frame);
    }

    protected function nativeCreateButton(string $nickname, string $title, Frame $frame): Button
    {
        $pointer = $this->log->nextPointer();
        $this->log->record('createButton', $pointer, $title);

        return new FakeButton($this->log, $pointer, $nickname, $this, $frame);
    }

    protected function nativeCreateEntry(string $nickname, string $text, Frame $frame): Entry
    {
        $pointer = $this->log->nextPointer();
        $this->log->record('createEntry', $pointer, $text);

        return new FakeEntry($this->log, $pointer, $nickname, $this, $frame, $text);
    }

    protected function nativeCreatePassword(string $nickname, string $text, Frame $frame): Password
    {
        $pointer = $this->log->nextPointer();
        $this->log->record('createPassword', $pointer, $text);

        return new FakePassword($this->log, $pointer, $nickname, $this, $frame, $text);
    }

    protected function nativeCreateCheckbox(string $nickname, string $title, bool $checked, Frame $frame): Checkbox
    {
        $pointer = $this->log->nextPointer();
        $this->log->record('createCheckbox', $pointer, $title, $checked);

        return new FakeCheckbox($this->log, $pointer, $nickname, $this, $frame, $checked);
    }

    protected function nativeCreateRadio(string $nickname, string $title, string $group, Frame $frame): Radio
    {
        $pointer = $this->log->nextPointer();
        $this->log->record('createRadio', $pointer, $title, $group);

        return new FakeRadio($this->log, $pointer, $nickname, $this, $frame, $group);
    }

    protected function nativeCreateSlider(string $nickname, float $min, float $max, float $value, Frame $frame): Slider
    {
        $pointer = $this->log->nextPointer();
        $this->log->record('createSlider', $pointer, $min, $max, $value);

        return new FakeSlider($this->log, $pointer, $nickname, $this, $frame, $min, $max, $value);
    }

    protected function nativeCreateSwitch(string $nickname, bool $on, Frame $frame): SwitchControl
    {
        $pointer = $this->log->nextPointer();
        $this->log->record('createSwitch', $pointer, $on);

        return new FakeSwitch($this->log, $pointer, $nickname, $this, $frame, $on);
    }

    protected function nativeCreateProgress(string $nickname, float $fraction, Frame $frame): Progress
    {
        $pointer = $this->log->nextPointer();
        $this->log->record('createProgress', $pointer, $fraction);

        return new FakeProgress($this->log, $pointer, $nickname, $this, $frame);
    }

    protected function nativeCreateSpinner(string $nickname, Frame $frame): Spinner
    {
        $pointer = $this->log->nextPointer();
        $this->log->record('createSpinner', $pointer);

        return new FakeSpinner($this->log, $pointer, $nickname, $this, $frame);
    }

    protected function nativeCreateDropdown(string $nickname, array $items, int $selected, Frame $frame): Dropdown
    {
        $pointer = $this->log->nextPointer();
        $this->log->record('createDropdown', $pointer, $items, $selected);

        return new FakeDropdown($this->log, $pointer, $nickname, $this, $frame, $items, $selected);
    }

    protected function nativeCreateText(string $nickname, string $text, bool $editable, Frame $frame): Text
    {
        $pointer = $this->log->nextPointer();
        $this->log->record('createText', $pointer, $text, $editable);

        return new FakeText($this->log, $pointer, $nickname, $this, $frame, $text, $editable);
    }

    protected function nativeCreateImage(string $nickname, string $path, Frame $frame): ?Image
    {
        if (! $this->loadImages) {
            return null;
        }

        $pointer = $this->log->nextPointer();
        $this->log->record('createImage', $pointer, $path);

        return new FakeImage($this->log, $pointer, $nickname, $this, $frame, $path);
    }

    protected function nativeCreateScroll(string $nickname, Size $content, Frame $frame): Scroll
    {
        $pointer = $this->log->nextPointer();
        $inner = $this->log->nextPointer();
        $this->log->record('createScroll', $pointer, $content->width, $content->height);

        return new FakeScroll($this->log, $pointer, $nickname, $this, $frame, $inner, $content);
    }

    protected function nativeCreateSplit(string $nickname, Orientation $orientation, Frame $frame): Split
    {
        $pointer = $this->log->nextPointer();
        $this->log->record('createSplit', $pointer, $orientation);
        $split = new FakeSplit($this->log, $pointer, $nickname, $this, $frame, $orientation);
        $first = new FakePane($this->log, $this->log->nextPointer(), 'first', $split, new Frame(0, 0, 0, 0));
        $second = new FakePane($this->log, $this->log->nextPointer(), 'second', $split, new Frame(0, 0, 0, 0));
        $split->mount($first, $second);

        return $split;
    }

    protected function nativeCreateTabs(string $nickname, Frame $frame): Tabs
    {
        $pointer = $this->log->nextPointer();
        $this->log->record('createTabs', $pointer);

        return new FakeTabs($this->log, $pointer, $nickname, $this, $frame);
    }

    protected function nativeCreatePopover(string $nickname, Size $size): Popover
    {
        $pointer = $this->log->nextPointer();
        $inner = $this->log->nextPointer();
        $this->log->record('createPopover', $pointer, $size->width, $size->height);

        return new FakePopover($this->log, $pointer, $nickname, $this, new Frame(0, 0, $size->width, $size->height), $inner);
    }
}
