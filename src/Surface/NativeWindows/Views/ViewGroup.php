<?php

namespace Surface\NativeWindows\Views;

use Surface\Contracts\NativeWindows\Views\OSButton;
use Surface\Contracts\NativeWindows\Views\OSCheckbox;
use Surface\Contracts\NativeWindows\Views\OSDropdown;
use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\Contracts\NativeWindows\Views\OSImage;
use Surface\Contracts\NativeWindows\Views\OSLabel;
use Surface\Contracts\NativeWindows\Views\OSProgressBar;
use Surface\Contracts\NativeWindows\Views\OSScrollView;
use Surface\Contracts\NativeWindows\Views\OSSeparator;
use Surface\Contracts\NativeWindows\Views\OSSlider;
use Surface\Contracts\NativeWindows\Views\OSSpinner;
use Surface\Contracts\NativeWindows\Views\OSTextArea;
use Surface\Contracts\NativeWindows\Views\OSTextInput;
use Surface\Contracts\NativeWindows\Views\OSToggle;
use Surface\Contracts\NativeWindows\Views\OSToggleButton;
use Surface\Contracts\NativeWindows\Views\OSVideo;
use Surface\Contracts\NativeWindows\Views\OSView;

/**
 * Shared policy for every container node: the child roster, the layout
 * cascade, subtree removal, and conjure sugar.
 *
 * Children are conjured INTO a container and stay there for life — the
 * window still owns the name registry and every child answers to its
 * window-global name, but its coordinates and centering resolve against
 * this container's innerSize(). The engine parents the native under the
 * container's surface at mint time, so moving the container moves the
 * subtree natively; only engines that owe a coordinate inversion need the
 * cascade to re-resolve children when the container's own frame changes.
 *
 * Removal is terminal for the subtree: every child dies first, then the
 * container's native, and every name is freed.
 */
abstract class ViewGroup extends View implements OSGroup
{
    /** @var list<string> Window-registry names of the children, in conjure order. */
    protected array $child_names = [];

    public function registerChild(OSView $child): void
    {
        $this->child_names[] = $child->name();
    }

    public function children(): array
    {
        $children = [];
        foreach ($this->child_names as $name) {
            $child = $this->window->view($name);
            if (! is_null($child)) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * Re-resolve this container's own rules, then cascade into the
     * children — their centering and any engine inversion depend on the
     * frame that was just resolved.
     */
    public function relayout(): static
    {
        parent::relayout();

        foreach ($this->children() as $child) {
            $child->relayout();
        }

        return $this;
    }

    /** Children first, then the container. Terminal for the whole subtree. */
    public function remove(): void
    {
        foreach ($this->children() as $child) {
            $child->remove();
        }
        $this->child_names = [];

        parent::remove();
    }

    /*
    |--------------------------------------------------------------------------
    | Conjure sugar — container-relative coordinates
    |--------------------------------------------------------------------------
    */

    public function label(string $name, string $text, int $x, int $y, int $width, int $height): OSLabel
    {
        return $this->window->label($name, $text, $x, $y, $width, $height, in: $this);
    }

    public function button(string $name, string $label, int $x, int $y, int $width, int $height): OSButton
    {
        return $this->window->button($name, $label, $x, $y, $width, $height, in: $this);
    }

    public function spinner(string $name, int $x, int $y, int $width, int $height): OSSpinner
    {
        return $this->window->spinner($name, $x, $y, $width, $height, in: $this);
    }

    public function image(string $name, ?string $path, int $x, int $y, int $width, int $height): OSImage
    {
        return $this->window->image($name, $path, $x, $y, $width, $height, in: $this);
    }

    public function video(string $name, ?string $path, int $x, int $y, int $width, int $height): OSVideo
    {
        return $this->window->video($name, $path, $x, $y, $width, $height, in: $this);
    }

    public function textInput(string $name, string $value, int $x, int $y, int $width, int $height, ?string $placeholder = null, bool $secret = false): OSTextInput
    {
        return $this->window->textInput($name, $value, $x, $y, $width, $height, $placeholder, $secret, in: $this);
    }

    public function textArea(string $name, string $value, int $x, int $y, int $width, int $height): OSTextArea
    {
        return $this->window->textArea($name, $value, $x, $y, $width, $height, in: $this);
    }

    public function slider(string $name, float $min, float $max, float $value, int $x, int $y, int $width, int $height): OSSlider
    {
        return $this->window->slider($name, $min, $max, $value, $x, $y, $width, $height, in: $this);
    }

    public function toggle(string $name, bool $on, int $x, int $y, int $width, int $height): OSToggle
    {
        return $this->window->toggle($name, $on, $x, $y, $width, $height, in: $this);
    }

    public function toggleButton(string $name, string $label, bool $pressed, int $x, int $y, int $width, int $height): OSToggleButton
    {
        return $this->window->toggleButton($name, $label, $pressed, $x, $y, $width, $height, in: $this);
    }

    public function checkbox(string $name, string $label, bool $checked, int $x, int $y, int $width, int $height): OSCheckbox
    {
        return $this->window->checkbox($name, $label, $checked, $x, $y, $width, $height, in: $this);
    }

    public function progressBar(string $name, float $progress, int $x, int $y, int $width, int $height): OSProgressBar
    {
        return $this->window->progressBar($name, $progress, $x, $y, $width, $height, in: $this);
    }

    public function dropdown(string $name, array $options, int $selected, int $x, int $y, int $width, int $height): OSDropdown
    {
        return $this->window->dropdown($name, $options, $selected, $x, $y, $width, $height, in: $this);
    }

    public function separator(string $name, int $x, int $y, int $width, int $height): OSSeparator
    {
        return $this->window->separator($name, $x, $y, $width, $height, in: $this);
    }

    public function group(string $name, int $x, int $y, int $width, int $height): OSGroup
    {
        return $this->window->group($name, $x, $y, $width, $height, in: $this);
    }

    public function scrollView(string $name, int $x, int $y, int $width, int $height): OSScrollView
    {
        return $this->window->scrollView($name, $x, $y, $width, $height, in: $this);
    }
}
