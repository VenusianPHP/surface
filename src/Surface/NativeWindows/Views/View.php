<?php

namespace Surface\NativeWindows\Views;

use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\Contracts\NativeWindows\Views\OSView;
use Surface\NativeWindows\Enums\PlacementRule;
use Surface\NativeWindows\Enums\SizeRule;
use Surface\NativeWindows\Windowable;

/**
 * Shared policy for every conjured node: the frame Surface believes in,
 * the RULES that produced it, and terminal removal. Engines supply the
 * native translation through the hooks.
 *
 * Placement is a rule, not a result. place() records an absolute origin,
 * center() records "centre me", hug() records "natural size"; relayout()
 * re-resolves whichever rules are set against the current content size.
 * That is what makes resize effortless — the window re-resolves, nothing
 * has to be told to move.
 *
 * The frame here is the truth. An engine hook receives top-left pixels and
 * does whatever its coordinate space needs — AppKit flips y against the
 * content height, GTK moves inside its GtkFixed.
 */
abstract class View implements OSView
{
    protected PlacementRule $placement = PlacementRule::ABSOLUTE;

    protected SizeRule $sizing = SizeRule::FIXED;

    protected ?Color $background = null;

    protected int $center_dx = 0;

    protected int $center_dy = 0;

    protected int $x = 0;

    protected int $y = 0;

    protected int $width = 0;

    protected int $height = 0;

    /**
     * The group this view was conjured into, or null for a window-content
     * view. Set once by Windowable during conjuring, before place().
     * @var OSGroup|null
     */
    protected ?OSGroup $host = null;

    /**
     * @param string $name Registry name inside the window.
     * @param Windowable $window The window this node lives in.
     */
    public function __construct(
        public readonly string $name,
        protected Windowable $window,
    ) {}

    /**
     * Conjure-time wiring: record the group this view lives inside. Not a
     * reparenting door — the native already sits in the group's surface.
     */
    public function hostIn(OSGroup $host): void
    {
        $this->host = $host;
    }

    public function hostedBy(): ?OSGroup
    {
        return $this->host;
    }

    /**
     * The space this view's rules resolve against: the host group's inner
     * size, or the window content for a top-level view. Engine frame hooks
     * that owe a coordinate inversion pay it against this same space.
     * @return array{int, int} [width, height]
     */
    public function layoutSpace(): array
    {
        return is_null($this->host) ? $this->window->contentSize() : $this->host->innerSize();
    }

    public function name(): string
    {
        return $this->name;
    }

    public function place(int $x, int $y, int $width, int $height): static
    {
        $this->placement = PlacementRule::ABSOLUTE;
        $this->sizing = SizeRule::FIXED;
        $this->x = $x;
        $this->y = $y;
        $this->width = $width;
        $this->height = $height;

        return $this->relayout();
    }

    public function frame(): array
    {
        return ['x' => $this->x, 'y' => $this->y, 'width' => $this->width, 'height' => $this->height];
    }

    public function hug(): static
    {
        $this->sizing = SizeRule::NATURAL;

        return $this->relayout();
    }

    /**
     * Centre inside the content, optionally offset — two centred views with
     * no offset land on the same spot. The offset is part of the rule and
     * re-resolves on every layout.
     */
    public function center(int $dx = 0, int $dy = 0): static
    {
        $this->placement = PlacementRule::CENTER;
        $this->center_dx = $dx;
        $this->center_dy = $dy;

        return $this->relayout();
    }

    /**
     * Centre horizontally only, optionally offset; y stays where place()
     * put it. The rule a top-anchored card lives by.
     */
    public function centerX(int $dx = 0): static
    {
        $this->placement = PlacementRule::CENTER_X;
        $this->center_dx = $dx;

        return $this->relayout();
    }

    /**
     * Re-resolve the stored rules against the current content size and push
     * the resulting frame to the engine. Windowable::relayout() calls this
     * for every view when the content resizes.
     * @return $this
     */
    public function relayout(): static
    {
        if ($this->sizing === SizeRule::NATURAL) {
            [$this->width, $this->height] = $this->measure();
        }

        if ($this->placement === PlacementRule::CENTER || $this->placement === PlacementRule::CENTER_X) {
            [$content_width, $content_height] = $this->layoutSpace();
            $this->x = (int) floor(($content_width - $this->width) / 2) + $this->center_dx;
        }

        if ($this->placement === PlacementRule::CENTER) {
            [, $content_height] = $this->layoutSpace();
            $this->y = (int) floor(($content_height - $this->height) / 2) + $this->center_dy;
        }

        $this->applyFrame($this->x, $this->y, $this->width, $this->height);

        return $this;
    }

    public function setBackground(Color $color): static
    {
        $this->background = $color;
        $this->applyBackground($color);

        return $this;
    }

    protected bool $visible = true;

    /**
     * Show or hide the view — a container takes its subtree with it, the
     * engine's own truth on both platforms. The frame and rules survive
     * hiding; the native write is change-only through applyVisible().
     */
    public function setVisible(bool $visible): static
    {
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

    public function remove(): void
    {
        $this->destroyNative();
        $this->window->forgetView($this->name);
    }

    /**
     * Put the native node at a top-left frame. The engine owns any inversion.
     * @return void
     */
    abstract protected function applyFrame(int $x, int $y, int $width, int $height): void;

    /**
     * The content's natural size in pixels.
     * @return array{int, int} [width, height]
     */
    abstract protected function measure(): array;

    /**
     * Destroy the native node. Terminal.
     * @return void
     */
    abstract protected function destroyNative(): void;

    /**
     * Write the visibility to the native node.
     * @return void
     */
    abstract protected function applyVisible(bool $visible): void;

    /**
     * Fill the node's frame with a colour. An engine with no honest path
     * for this node kind ignores it, stated in its own code.
     * @return void
     */
    abstract protected function applyBackground(Color $color): void;
}
