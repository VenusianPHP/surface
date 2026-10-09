<?php

namespace Surface\Windows;

use Closure;
use Surface\Bridge\BridgedToolkitSession;
use Surface\Contracts\Drawing\VSync;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Windows\Display;
use Surface\Contracts\Windows\DisplayMode;
use Surface\Contracts\Windows\Hdr;
use Surface\Contracts\Windows\Mail\WindowCloseRequested;
use Surface\Contracts\Windows\Mail\WindowClosed;
use Surface\Contracts\Windows\Mail\WindowDisplayChanged;
use Surface\Contracts\Windows\Mail\WindowExposed;
use Surface\Contracts\Windows\Mail\WindowFocused;
use Surface\Contracts\Windows\Mail\WindowFocusLost;
use Surface\Contracts\Windows\Mail\WindowFrameDue;
use Surface\Contracts\Windows\Mail\WindowModeChanged;
use Surface\Contracts\Windows\Mail\WindowMoved;
use Surface\Contracts\Windows\Mail\WindowOccluded;
use Surface\Contracts\Windows\Mail\WindowResized;
use Surface\Contracts\Windows\Mail\WindowScaleChanged;
use Surface\Contracts\Windows\ScaleFilter;
use Surface\Contracts\Windows\ScaleFit;
use Surface\Contracts\Windows\StagedWindow as StagedWindowContract;
use Surface\Contracts\Windows\WindowCapability;
use Surface\Contracts\Windows\WindowException;
use Surface\Contracts\Windows\WindowMode;
use Surface\Windows\Concerns\HostsDrawing;

/**
 * The staged window, toolkit-neutral: state, validation, capabilities, mail
 * and the shared drawing machinery. A stager supplies the native window
 * through the apply* and native* hooks and calls the mail hooks (resized(),
 * moved(), modeChanged(), focused(), …) from its native callbacks.
 *
 * A stager makes the native window from options() with the size, resizable,
 * borderless, always_on_top, focusable, transparent, and x/y or display
 * already applied, then calls stage(). It overrides the apply hook of each
 * optional capability it lists in nativeCapabilities(); the base never calls
 * the others.
 */
abstract class StagedWindow implements StagedWindowContract
{
    use HostsDrawing;

    private const array OPTIONS = [
        'title' => 'string',
        'visible' => 'bool',
        'resizable' => 'bool',
        'mode' => WindowMode::class,
        'display' => Display::class,
        'display_mode' => DisplayMode::class,
        'x' => 'int',
        'y' => 'int',
        'borderless' => 'bool',
        'always_on_top' => 'bool',
        'focusable' => 'bool',
        'transparent' => 'bool',
        'confirm_close' => 'bool',
        'vsync' => VSync::class,
    ];

    protected string $title;

    protected bool $visible = false;

    protected bool $open = true;

    protected bool $occluded = false;

    protected WindowMode $mode = WindowMode::Windowed;

    protected ?DisplayMode $exclusive = null;

    protected bool $resizable;

    protected bool $borderless;

    protected bool $always_on_top;

    protected bool $focusable;

    protected readonly bool $transparent;

    protected readonly bool $confirm_close;

    protected float $opacity = 1.0;

    protected VSync $vsync;

    protected ScaleFilter $filter = ScaleFilter::Linear;

    protected ScaleFit $fit = ScaleFit::Stretch;

    protected bool $kept_awake = false;

    /** @var array{int, int, int, int} */
    protected array $limits = [0, 0, 0, 0];

    /** @var array{float, float} */
    protected array $aspect = [0.0, 0.0];

    protected ?Closure $hit_test = null;

    /**
     * @param  array<string, mixed>  $options  From options(): validated, every key present.
     */
    public function __construct(
        protected readonly string $name,
        protected readonly BridgedToolkitSession $session,
        array $options,
    ) {
        $this->title = $options['title'];
        $this->resizable = $options['resizable'];
        $this->borderless = $options['borderless'];
        $this->always_on_top = $options['always_on_top'];
        $this->focusable = $options['focusable'];
        $this->transparent = $options['transparent'];
        $this->confirm_close = $options['confirm_close'];
        $this->vsync = $options['vsync'];
    }

    /**
     * Validate a driver's options and fill the defaults. Drivers call this
     * before making the native window, then stage() once it exists.
     *
     * @return array{title: string, visible: bool, resizable: bool, mode: WindowMode, display: ?Display, display_mode: ?DisplayMode, x: ?int, y: ?int, borderless: bool, always_on_top: bool, focusable: bool, transparent: bool, confirm_close: bool, vsync: VSync}
     */
    public static function options(array $options, string $name): array
    {
        $unknown = array_diff(array_keys($options), array_keys(self::OPTIONS));
        if ($unknown !== []) {
            $listed = implode(', ', array_map(fn (string $key): string => "'{$key}'", $unknown));
            $known = implode(', ', array_keys(self::OPTIONS));

            throw new WindowException("Staged window '{$name}' takes no option {$listed} (it takes: {$known}).");
        }
        foreach (self::OPTIONS as $key => $type) {
            if (array_key_exists($key, $options) && get_debug_type($options[$key]) !== $type) {
                throw new WindowException("Staged window '{$name}' option '{$key}' is a {$type}, got ".get_debug_type($options[$key]).'.');
            }
        }

        $options += [
            'title' => $name, 'visible' => true, 'resizable' => true, 'mode' => WindowMode::Windowed,
            'display' => null, 'display_mode' => null, 'x' => null, 'y' => null,
            'borderless' => false, 'always_on_top' => false, 'focusable' => true, 'transparent' => false,
            'confirm_close' => false, 'vsync' => VSync::On,
        ];

        if (is_null($options['x']) !== is_null($options['y'])) {
            throw new WindowException("Staged window '{$name}' takes options 'x' and 'y' together.");
        }
        if (! is_null($options['x']) && ! is_null($options['display'])) {
            throw new WindowException("Staged window '{$name}' takes 'x' and 'y' or 'display', not both.");
        }
        if (($options['mode'] === WindowMode::Exclusive) !== ! is_null($options['display_mode'])) {
            throw new WindowException("Staged window '{$name}' takes 'display_mode' with mode Exclusive, and Exclusive needs one.");
        }
        if (! is_null($options['display']) && ! is_null($options['display_mode']) && $options['display']->id !== $options['display_mode']->displayId) {
            throw new WindowException("Staged window '{$name}' option 'display_mode' belongs to display {$options['display_mode']->displayId}, not 'display' {$options['display']->id}.");
        }

        return $options;
    }

    /**
     * Apply the opening options the native window takes after it exists: the
     * title, vsync, the mode, shown if asked. An option needing a capability
     * the backend lacks destroys the native window, frees the name and throws;
     * no mail goes out for a window never handed over.
     *
     * @param  array<string, mixed>  $options  From options().
     */
    protected function stage(array $options): void
    {
        $needs = [];
        if (! is_null($options['x'])) {
            $needs[] = [WindowCapability::Position, "open at {$options['x']},{$options['y']}"];
        }
        // Covering a display in fullscreen needs no position; placing a window on one does.
        if (! is_null($options['display']) && ! in_array($options['mode'], [WindowMode::Fullscreen, WindowMode::Exclusive], true)) {
            $needs[] = [WindowCapability::Position, "open on display {$options['display']->id}"];
        }
        if ($options['mode'] === WindowMode::Exclusive) {
            $needs[] = [WindowCapability::ExclusiveFullscreen, 'open in exclusive fullscreen'];
        }
        foreach ($needs as [$capability, $doing]) {
            if (! $this->supports($capability)) {
                $this->open = false;
                $this->destroyNative();
                $this->forget();

                throw $this->lacks($doing);
            }
        }

        $this->applyTitle($options['title']);
        $this->applyVsync($this->vsync);
        if ($options['mode'] !== WindowMode::Windowed) {
            $this->setMode($options['mode'], $options['display_mode'] ?? ($options['mode'] === WindowMode::Fullscreen ? $options['display'] : null));
        }
        if ($options['visible']) {
            $this->show();
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function capabilities(): array
    {
        return $this->nativeCapabilities();
    }

    public function supports(WindowCapability $capability): bool
    {
        return in_array($capability, $this->nativeCapabilities(), true);
    }

    public function title(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->live();
        $this->title = $title;
        $this->applyTitle($title);

        return $this;
    }

    public function size(): array
    {
        return $this->live()->nativeSize();
    }

    public function scale(): float
    {
        return $this->live()->nativeScale();
    }

    public function resize(int $width, int $height): static
    {
        $this->live();
        $this->applyResize($width, $height);

        return $this;
    }

    public function position(): array
    {
        return $this->live()->nativePosition();
    }

    public function move(int $x, int $y): static
    {
        $this->live()->needs(WindowCapability::Position, "move to {$x},{$y}");
        $this->applyPosition($x, $y);

        return $this;
    }

    public function setLimits(int $minWidth, int $minHeight, int $maxWidth = 0, int $maxHeight = 0): static
    {
        $this->live();
        if (min($minWidth, $minHeight, $maxWidth, $maxHeight) < 0) {
            throw new WindowException("{$this->hostName()} size limits are 0 or more, got {$minWidth}x{$minHeight} to {$maxWidth}x{$maxHeight}.");
        }
        if (($maxWidth > 0 && $minWidth > $maxWidth) || ($maxHeight > 0 && $minHeight > $maxHeight)) {
            throw new WindowException("{$this->hostName()} minimum size {$minWidth}x{$minHeight} is past its maximum {$maxWidth}x{$maxHeight}.");
        }
        $this->limits = [$minWidth, $minHeight, $maxWidth, $maxHeight];
        $this->applyLimits($minWidth, $minHeight, $maxWidth, $maxHeight);

        return $this;
    }

    public function limits(): array
    {
        return $this->limits;
    }

    public function setAspectRatio(float $min, float $max): static
    {
        $this->live();
        if ($min < 0.0 || $max < 0.0 || ($max > 0.0 && $min > $max)) {
            throw new WindowException("{$this->hostName()} aspect ratio runs from 0.0 up, minimum first, got {$min} to {$max}.");
        }
        $this->aspect = [$min, $max];
        $this->applyAspectRatio($min, $max);

        return $this;
    }

    public function aspectRatio(): array
    {
        return $this->aspect;
    }

    public function safeArea(): Region
    {
        return $this->live()->nativeSafeArea();
    }

    public function show(): static
    {
        $this->live();
        $this->visible = true;
        $this->applyVisible(true);

        return $this;
    }

    public function hide(): static
    {
        $this->live();
        $this->visible = false;
        $this->applyVisible(false);

        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function isOccluded(): bool
    {
        return $this->occluded;
    }

    public function setMode(WindowMode $mode, Display|DisplayMode|null $on = null): static
    {
        $this->live();
        match ($mode) {
            WindowMode::Exclusive => $on instanceof DisplayMode
                ? $this->needs(WindowCapability::ExclusiveFullscreen, 'go exclusive fullscreen')
                : throw new WindowException("{$this->hostName()} goes exclusive fullscreen at a DisplayMode, got ".get_debug_type($on).'.'),
            WindowMode::Fullscreen => $on instanceof DisplayMode
                ? throw new WindowException("{$this->hostName()} covers a Display in borderless fullscreen; a DisplayMode is for Exclusive.")
                : null,
            default => is_null($on) ? null : throw new WindowException("{$this->hostName()} takes no display for mode {$mode->value}."),
        };

        $same = $this->mode === $mode && (
            ($mode === WindowMode::Exclusive && $this->exclusive?->equals($on))
            || ($mode !== WindowMode::Exclusive && is_null($on))
        );
        if ($same) {
            return $this;
        }
        $this->mode = $mode;
        $this->exclusive = $on instanceof DisplayMode ? $on : null;
        $this->applyMode($mode, $on);

        return $this;
    }

    public function mode(): WindowMode
    {
        return $this->mode;
    }

    public function exclusiveMode(): ?DisplayMode
    {
        return $this->exclusive;
    }

    public function display(): Display
    {
        return $this->live()->nativeDisplay();
    }

    public function moveToDisplay(Display $display): static
    {
        $this->live();
        match ($this->mode) {
            WindowMode::Fullscreen => $this->applyMode(WindowMode::Fullscreen, $display),
            WindowMode::Exclusive => throw new WindowException("{$this->hostName()} is exclusive fullscreen: setMode(WindowMode::Exclusive, a mode of display {$display->id}) moves it."),
            default => $this->needs(WindowCapability::Position, "move to display {$display->id}")->applyDisplay($display),
        };

        return $this;
    }

    public function displayModes(): array
    {
        return $this->live()->nativeDisplayModes();
    }

    public function setResizable(bool $resizable): static
    {
        $this->live();
        $this->resizable = $resizable;
        $this->applyResizable($resizable);

        return $this;
    }

    public function isResizable(): bool
    {
        return $this->resizable;
    }

    public function setBorderless(bool $borderless): static
    {
        $this->live();
        $this->borderless = $borderless;
        $this->applyBorderless($borderless);

        return $this;
    }

    public function isBorderless(): bool
    {
        return $this->borderless;
    }

    public function setAlwaysOnTop(bool $onTop): static
    {
        $this->live()->needs(WindowCapability::AlwaysOnTop, $onTop ? 'stay on top' : 'stop staying on top');
        $this->always_on_top = $onTop;
        $this->applyAlwaysOnTop($onTop);

        return $this;
    }

    public function isAlwaysOnTop(): bool
    {
        return $this->always_on_top;
    }

    public function setFocusable(bool $focusable): static
    {
        $this->live()->needs(WindowCapability::Focusable, $focusable ? 'become focusable' : 'refuse focus');
        $this->focusable = $focusable;
        $this->applyFocusable($focusable);

        return $this;
    }

    public function isFocusable(): bool
    {
        return $this->focusable;
    }

    public function isTransparent(): bool
    {
        return $this->transparent;
    }

    public function setOpacity(float $opacity): static
    {
        $this->live()->needs(WindowCapability::Opacity, 'set its opacity');
        if ($opacity < 0.0 || $opacity > 1.0) {
            throw new WindowException("{$this->hostName()} opacity runs 0.0 to 1.0, got {$opacity}.");
        }
        $this->opacity = $opacity;
        $this->applyOpacity($opacity);

        return $this;
    }

    public function opacity(): float
    {
        return $this->opacity;
    }

    public function setVsync(VSync $vsync): static
    {
        $this->live();
        $this->vsync = $vsync;
        $this->applyVsync($vsync);
        $this->lent?->changeVsync($vsync);

        return $this;
    }

    public function vsync(): VSync
    {
        return $this->vsync;
    }

    public function setScaling(ScaleFilter $filter, ScaleFit $fit): static
    {
        $this->live();
        $this->filter = $filter;
        $this->fit = $fit;
        $this->lent?->changeScaling($filter, $fit);
        $this->shown = null;                                           // the next present() shows the whole frame again

        return $this;
    }

    public function scaling(): array
    {
        return [$this->filter, $this->fit];
    }

    public function keepAwake(bool $awake): static
    {
        $this->live()->needs(WindowCapability::KeepAwake, $awake ? 'keep the display awake' : 'let the display sleep');
        $this->kept_awake = $awake;
        $this->applyKeepAwake($awake);

        return $this;
    }

    public function isKeptAwake(): bool
    {
        return $this->kept_awake;
    }

    public function requestAttention(): static
    {
        $this->live()->needs(WindowCapability::Attention, 'request attention');
        $this->applyAttention();

        return $this;
    }

    public function setIcon(string $rgba8, int $width, int $height): static
    {
        $this->live()->needs(WindowCapability::Icon, 'set an icon');
        if ($width < 1 || $height < 1 || strlen($rgba8) !== $width * $height * 4) {
            throw new WindowException("{$this->hostName()} icon of {$width}x{$height} is ".max(0, $width * $height * 4).' RGBA8 bytes, got '.strlen($rgba8).'.');
        }
        $this->applyIcon($rgba8, $width, $height);

        return $this;
    }

    public function hitTest(?Closure $test): static
    {
        $this->live()->needs(WindowCapability::HitTest, 'take a hit test');
        $this->hit_test = $test;
        $this->applyHitTest($test);

        return $this;
    }

    public function hdr(): ?Hdr
    {
        return $this->live()->nativeHdr();
    }

    public function close(): void
    {
        if (! $this->open) {
            return;
        }
        $this->reclaim();
        $this->destroyNative();
        $this->closed();
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    public function session(): BridgedToolkitSession
    {
        return $this->session;
    }

    /**
     * Where a framebuffer of $width × $height pixels lands in the window, in
     * window pixels, by the scaling fit. Stagers draw the frame into this rect
     * with the scaling filter and clear the rest.
     */
    public function presentRect(int $width, int $height): Region
    {
        [$across, $down] = $this->pixelSize();

        return $this->fit->rect($across, $down, $width, $height);
    }

    /**
     * The native window has a new size in points: latest wins until the pump flushes.
     * A stager calls this from its resize callback; applyResize() ends here too.
     * Nothing once closed: a toolkit may still deliver a queued resize while it destroys the window.
     */
    protected function resized(int $width, int $height): void
    {
        if (! $this->open) {
            return;
        }
        $this->session->postLatest("window.resized.{$this->name}", new WindowResized($this->name, $width, $height));
    }

    /** The native window moved, in points on the desktop. Latest wins; nothing once closed. */
    protected function moved(int $x, int $y): void
    {
        if (! $this->open) {
            return;
        }
        $this->session->postLatest("window.moved.{$this->name}", new WindowMoved($this->name, $x, $y));
    }

    /**
     * The native window entered $mode, asked for or by the user (the zoom
     * button, a minimize). applyMode() ends here too. An Exclusive entry keeps
     * the display mode setMode() asked for; nothing once closed.
     */
    protected function modeChanged(WindowMode $mode): void
    {
        if (! $this->open) {
            return;
        }
        $this->mode = $mode;
        if ($mode !== WindowMode::Exclusive) {
            $this->exclusive = null;
        }
        $this->session->post(new WindowModeChanged($this->name, $mode));
    }

    /** The native window became key. A stager calls this from its focus callback; nothing once closed. */
    protected function focused(): void
    {
        if (! $this->open) {
            return;
        }
        $this->session->post(new WindowFocused($this->name));
    }

    /** The native window stopped being key; nothing once closed. */
    protected function focusLost(): void
    {
        if (! $this->open) {
            return;
        }
        $this->session->post(new WindowFocusLost($this->name));
    }

    /** Nothing drawn in the window shows now; nothing once closed or already occluded. */
    protected function occluded(): void
    {
        if (! $this->open || $this->occluded) {
            return;
        }
        $this->occluded = true;
        $this->session->post(new WindowOccluded($this->name));
    }

    /** The window shows again; nothing once closed or not occluded. */
    protected function exposed(): void
    {
        if (! $this->open || ! $this->occluded) {
            return;
        }
        $this->occluded = false;
        $this->session->post(new WindowExposed($this->name));
    }

    /** The window is now mostly on display $displayId; nothing once closed. */
    protected function displayChanged(int $displayId): void
    {
        if (! $this->open) {
            return;
        }
        $this->session->post(new WindowDisplayChanged($this->name, $displayId));
    }

    /** Device pixels per point changed; nothing once closed. */
    protected function scaleChanged(float $scale): void
    {
        if (! $this->open) {
            return;
        }
        $this->session->post(new WindowScaleChanged($this->name, $scale));
    }

    /** A FrameClock stager's display link fired, in seconds. Latest wins; nothing once closed. */
    protected function frameDue(float $timestamp, float $targetTimestamp): void
    {
        if (! $this->open) {
            return;
        }
        $this->session->postLatest("window.frame-due.{$this->name}", new WindowFrameDue($this->name, $timestamp, $targetTimestamp));
    }

    /**
     * The user asked to close the window (the close box, the toolkit's
     * should-close). It closes, unless opened with confirm_close: then
     * WindowCloseRequested goes out and the app decides with close().
     */
    protected function closeRequested(): void
    {
        if (! $this->open) {
            return;
        }
        if ($this->confirm_close) {
            $this->session->post(new WindowCloseRequested($this->name));

            return;
        }
        $this->close();
    }

    /**
     * The one close path, from close() or a native close: a lent surface is
     * reclaimed while the native window still lives, then the pending latest
     * mail goes out, then WindowClosed, then the stager forgets the name.
     *
     * A stager calls this itself only when the toolkit destroys the window on
     * its own (AppKit's windowWillClose), from that callback, while the native
     * surface still exists. A toolkit that only asks to close (SDL 3's
     * close-requested event, GLFW's should-close flag) leaves the window alive:
     * its stager calls closeRequested() instead.
     */
    protected function closed(): void
    {
        if (! $this->open) {
            return;
        }
        $this->reclaim();
        $this->open = false;
        $this->visible = false;
        $this->session->flushLatest();
        $this->session->post(new WindowClosed($this->name));
        $this->forget();
    }

    protected function lendingVsync(): VSync
    {
        return $this->vsync;
    }

    protected function lendingScaling(): array
    {
        return [$this->filter, $this->fit];
    }

    /** Read through the native window while it is open; null once closed. */
    protected function lendingHdr(): ?Hdr
    {
        return $this->open ? $this->nativeHdr() : null;
    }

    /** Tell the stager this name is free. */
    abstract protected function forget(): void;

    protected function live(): static
    {
        if (! $this->open) {
            throw new WindowException("Staged window '{$this->name}' was closed.");
        }

        return $this;
    }

    /** @throws WindowException when the backend lacks $capability */
    protected function needs(WindowCapability $capability, string $doing): static
    {
        if (! $this->supports($capability)) {
            throw $this->lacks($doing);
        }

        return $this;
    }

    private function lacks(string $doing): WindowException
    {
        return new WindowException("{$this->hostName()} cannot {$doing}: the {$this->backend()} backend does not support it.");
    }

    protected function hostName(): string
    {
        return "Staged window '{$this->name}'";
    }

    protected function hostKind(): string
    {
        return 'staged window';
    }

    /** @return list<WindowCapability> what this backend does beyond the base set */
    abstract protected function nativeCapabilities(): array;

    /** @return array{int, int} */
    abstract protected function nativePosition(): array;

    abstract protected function nativeSafeArea(): Region;

    abstract protected function nativeDisplay(): Display;

    /** @return list<DisplayMode> */
    abstract protected function nativeDisplayModes(): array;

    abstract protected function nativeHdr(): ?Hdr;

    abstract protected function applyTitle(string $title): void;

    abstract protected function applyVisible(bool $visible): void;

    /**
     * Enter $mode: covering Display $on (null: the current one) for Fullscreen,
     * at DisplayMode $on for Exclusive. The stager's native callback then calls
     * modeChanged().
     */
    abstract protected function applyMode(WindowMode $mode, Display|DisplayMode|null $on): void;

    /** Ask the native window for a new size in points; the stager's resize callback then calls resized(). */
    abstract protected function applyResize(int $width, int $height): void;

    /** Centre the windowed native window on $display. */
    abstract protected function applyDisplay(Display $display): void;

    abstract protected function applyLimits(int $minWidth, int $minHeight, int $maxWidth, int $maxHeight): void;

    abstract protected function applyAspectRatio(float $min, float $max): void;

    abstract protected function applyResizable(bool $resizable): void;

    abstract protected function applyBorderless(bool $borderless): void;

    /** The vsync of the window's own present(). */
    abstract protected function applyVsync(VSync $vsync): void;

    /** Destroy the native window. A stager whose toolkit calls back on destroy guards closed() against the second call; closed() is idempotent. */
    abstract protected function destroyNative(): void;

    /** Position. */
    protected function applyPosition(int $x, int $y): void
    {
        throw $this->unapplied(WindowCapability::Position);
    }

    /** AlwaysOnTop. */
    protected function applyAlwaysOnTop(bool $onTop): void
    {
        throw $this->unapplied(WindowCapability::AlwaysOnTop);
    }

    /** Focusable. */
    protected function applyFocusable(bool $focusable): void
    {
        throw $this->unapplied(WindowCapability::Focusable);
    }

    /** Opacity. */
    protected function applyOpacity(float $opacity): void
    {
        throw $this->unapplied(WindowCapability::Opacity);
    }

    /** KeepAwake. */
    protected function applyKeepAwake(bool $awake): void
    {
        throw $this->unapplied(WindowCapability::KeepAwake);
    }

    /** Attention. */
    protected function applyAttention(): void
    {
        throw $this->unapplied(WindowCapability::Attention);
    }

    /** Icon. */
    protected function applyIcon(string $rgba8, int $width, int $height): void
    {
        throw $this->unapplied(WindowCapability::Icon);
    }

    /**
     * HitTest. The stager asks $test for each press and maps the HitArea onto its toolkit.
     *
     * @param  (Closure(int, int): \Surface\Contracts\Windows\HitArea)|null  $test
     */
    protected function applyHitTest(?Closure $test): void
    {
        throw $this->unapplied(WindowCapability::HitTest);
    }

    /** A stager listed $capability without overriding its apply hook. */
    private function unapplied(WindowCapability $capability): WindowException
    {
        return new WindowException(static::class." lists the {$capability->value} capability but does not override its apply hook.");
    }
}
