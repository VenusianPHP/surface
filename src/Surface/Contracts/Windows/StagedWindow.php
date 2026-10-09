<?php

namespace Surface\Contracts\Windows;

use Closure;
use Surface\Contracts\Drawing\VSync;
use Surface\Contracts\Drawing\WindowOutput;
use Surface\Contracts\Framebuffers\Region;

/**
 * A window that is the output: no primitives, one surface filling it. Draw
 * into framebuffer() with Velvet, or lend() a native surface to a GPU engine;
 * present() shows the result either way.
 *
 * A call that needs a WindowCapability the backend lacks throws
 * WindowException naming the backend; capabilities() lists what it has.
 *
 * Mail: WindowResized, WindowMoved and WindowFrameDue (latest wins);
 * WindowFocused, WindowFocusLost, WindowModeChanged, WindowOccluded,
 * WindowExposed, WindowDisplayChanged, WindowScaleChanged,
 * WindowCloseRequested (confirm_close only) and WindowClosed in order.
 */
interface StagedWindow extends OSWindow, WindowOutput
{
    public function name(): string;

    /** The stager and what it runs on: "sdl3/wayland", "appkit/cocoa", "glfw/x11". */
    public function backend(): string;

    /** @return list<WindowCapability> */
    public function capabilities(): array;

    public function supports(WindowCapability $capability): bool;

    public function title(): string;

    public function setTitle(string $title): static;

    /** @return array{int, int} width and height in points */
    public function size(): array;

    /** Device pixels per point. */
    public function scale(): float;

    /** Points. The stager posts WindowResized once the native window has the new size. */
    public function resize(int $width, int $height): static;

    /** @return array{int, int} x and y in points on the desktop */
    public function position(): array;

    /** Points on the desktop. Needs Position. */
    public function move(int $x, int $y): static;

    /**
     * Smallest and largest size in points; 0 for no bound on that side.
     *
     * @throws WindowException for a negative bound or a minimum past its maximum
     */
    public function setLimits(int $minWidth, int $minHeight, int $maxWidth = 0, int $maxHeight = 0): static;

    /** @return array{int, int, int, int} min width, min height, max width, max height; 0 is no bound */
    public function limits(): array;

    /**
     * Width ÷ height kept between $min and $max while the user resizes; 0.0 and 0.0 for none.
     *
     * @throws WindowException for a negative ratio or a minimum past its maximum
     */
    public function setAspectRatio(float $min, float $max): static;

    /** @return array{float, float} */
    public function aspectRatio(): array;

    /** The part of the window no notch, rounded corner or system bar covers, in points from its top-left. */
    public function safeArea(): Region;

    public function show(): static;

    public function hide(): static;

    public function isVisible(): bool;

    /** Whether this window has keyboard focus. */
    public function isKey(): bool;

    /** Covered, minimized or on another Space, as the stager last reported. */
    public function isOccluded(): bool;

    /**
     * Windowed, maximized, minimized, borderless fullscreen or exclusive.
     * Fullscreen takes the Display to cover (null: the one the window is on);
     * Exclusive takes the DisplayMode to switch to and needs ExclusiveFullscreen.
     *
     * @throws WindowException for Exclusive without a DisplayMode, or a target the mode does not take
     */
    public function setMode(WindowMode $mode, Display|DisplayMode|null $on = null): static;

    public function mode(): WindowMode;

    /** The mode exclusive fullscreen runs at; null unless the window is Exclusive. */
    public function exclusiveMode(): ?DisplayMode;

    /** The display the window is mostly on. */
    public function display(): Display;

    /** Centre the window on $display. Windowed, this needs Position; fullscreen, it moves the window to cover $display. */
    public function moveToDisplay(Display $display): static;

    /** @return list<DisplayMode> what exclusive fullscreen can run at on the window's display */
    public function displayModes(): array;

    public function setResizable(bool $resizable): static;

    public function isResizable(): bool;

    public function setBorderless(bool $borderless): static;

    public function isBorderless(): bool;

    /** Needs AlwaysOnTop. */
    public function setAlwaysOnTop(bool $onTop): static;

    public function isAlwaysOnTop(): bool;

    /** Needs Focusable. */
    public function setFocusable(bool $focusable): static;

    public function isFocusable(): bool;

    /** Whether the window was opened with a transparent background; fixed at open. */
    public function isTransparent(): bool;

    /**
     * 0.0 (invisible) to 1.0. Needs Opacity.
     *
     * @throws WindowException outside 0.0 to 1.0
     */
    public function setOpacity(float $opacity): static;

    public function opacity(): float;

    /** Applied to this window's own present(), and handed to the surface it lends. */
    public function setVsync(VSync $vsync): static;

    public function vsync(): VSync;

    /** How a framebuffer whose size differs from the window's is shown. */
    public function setScaling(ScaleFilter $filter, ScaleFit $fit): static;

    /** @return array{ScaleFilter, ScaleFit} */
    public function scaling(): array;

    /** Hold off the screen saver and display sleep while true. Needs KeepAwake. */
    public function keepAwake(bool $awake): static;

    public function isKeptAwake(): bool;

    /** Bounce, flash or badge the window until it is focused. Needs Attention. */
    public function requestAttention(): static;

    /**
     * Needs Icon.
     *
     * @param  string  $rgba8  $width × $height × 4 bytes, top row first
     *
     * @throws WindowException when $rgba8 is not that long
     */
    public function setIcon(string $rgba8, int $width, int $height): static;

    /**
     * Needs HitTest. The stager asks $test for each press in the window, with the
     * point in points from its top-left; null removes it.
     *
     * @param  (Closure(int, int): HitArea)|null  $test
     */
    public function hitTest(?Closure $test): static;

    /** HDR state of the window's display; null when the backend does not report it. */
    public function hdr(): ?Hdr;

    /** Terminal: a lent surface is reclaimed, the native window destroyed, WindowClosed posted; every later call throws. */
    public function close(): void;

    public function isOpen(): bool;
}
