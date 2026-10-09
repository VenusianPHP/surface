<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\SurfaceKind;
use Surface\Contracts\Drawing\VSync;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Windows\Display;
use Surface\Contracts\Windows\DisplayMode;
use Surface\Contracts\Windows\Hdr;
use Surface\Contracts\Windows\StagedWindow as StagedWindowContract;
use Surface\Contracts\Windows\StagedWindowDriver;
use Surface\Contracts\Windows\WindowCapability;
use Surface\Contracts\Windows\WindowException;
use Surface\Contracts\Windows\WindowMode;
use Surface\Windows\StagedWindow;
use Venusian\Surface\Tests\Fixtures\FakeSession;

/** Two displays: 1 is 1440x900 at scale 2, 2 is 1920x1080 at scale 1 to its right. */
function fakeDisplays(): array
{
    $retina = new DisplayMode(1, 1440, 900, 2.0, 60.0);
    $plain = new DisplayMode(2, 1920, 1080, 1.0, 60.0);

    return [
        new Display(1, 'Built-in', new Region(0, 0, 1440, 900), new Region(0, 25, 1440, 875), 2.0, $retina, $retina, new Hdr(true, 1.0, 4.0)),
        new Display(2, 'External', new Region(1440, 0, 1920, 1080), new Region(1440, 0, 1920, 1080), 1.0, $plain, $plain),
    ];
}

/**
 * A staged window at scale 2 that logs every native call, lends what $lends
 * lists and has what $capabilities lists. Native callbacks are synchronous.
 */
final class FakeStagedWindow extends StagedWindow
{
    /** @var list<string> */
    public array $log = [];

    /** @var array{int, int} */
    public array $measures;

    /** @var array{int, int} */
    public array $place = [100, 80];

    public float $scale = 2.0;

    public bool $key = false;

    public int $on_display = 1;

    /** @var list<array{string, int, int}> */
    public array $pixels = [];

    /** @var list<array{int, int, int, int, array}> */
    public array $addresses = [];

    /** @var list<SurfaceKind> */
    public array $lends = [];

    public static array $capabilities = [];

    public function __construct(string $name, FakeSession $session, int $width, int $height, array $options, private readonly FakeStager $stager)
    {
        parent::__construct($name, $session, $options);
        $this->measures = [$width, $height];
    }

    /** What the driver does after the native window exists. */
    public function open(array $options): void
    {
        $this->stage($options);
    }

    public function backend(): string { return 'fake/test'; }

    public function isKey(): bool { return $this->key; }

    /** The native window became key. */
    public function focus(): void { $this->focused(); }

    public function blur(): void { $this->focusLost(); }

    /** The native resize callback, arriving on its own. */
    public function nativeResized(int $width, int $height): void { $this->resized($width, $height); }

    public function nativeMoved(int $x, int $y): void { $this->place = [$x, $y]; $this->moved($x, $y); }

    /** The user maximized, minimized or restored it. */
    public function nativeMode(WindowMode $mode): void { $this->modeChanged($mode); }

    public function nativeOccluded(bool $covered): void { $covered ? $this->occluded() : $this->exposed(); }

    public function nativeScreenMove(int $id, float $scale): void
    {
        $this->on_display = $id;
        $this->displayChanged($id);
        if ($scale !== $this->scale) {
            $this->scale = $scale;
            $this->scaleChanged($scale);
        }
    }

    public function nativeFrame(float $now, float $next): void { $this->frameDue($now, $next); }

    /** The close box was hit. */
    public function nativeCloseBox(): void { $this->closeRequested(); }

    /** The toolkit is destroying the native window on its own (AppKit's windowWillClose). */
    public function nativeClosed(): void { $this->closed(); }

    public function surfaces(): array { return $this->lends; }

    protected function nativeCapabilities(): array { return self::$capabilities; }

    protected function makeSurface(SurfaceKind $kind, array $handles): array
    {
        $this->log[] = "surface:{$kind->value}:".json_encode($handles);

        return [$kind->handle() => 0xC0FFEE];
    }

    protected function removeSurface(SurfaceKind $kind): void { $this->log[] = "unsurface:{$kind->value}"; }

    protected function nativeSize(): array { return $this->measures; }

    protected function nativeScale(): float { return $this->scale; }

    protected function nativePosition(): array { return $this->place; }

    protected function nativeSafeArea(): Region { return new Region(0, 16, $this->measures[0], $this->measures[1] - 16); }

    protected function nativeDisplay(): Display { return fakeDisplays()[$this->on_display - 1]; }

    protected function nativeDisplayModes(): array
    {
        return [new DisplayMode($this->on_display, 1440, 900, 2.0, 60.0), new DisplayMode($this->on_display, 1280, 800, 2.0, 60.0)];
    }

    protected function nativeHdr(): ?Hdr { return $this->nativeDisplay()->hdr; }

    protected function applyPixels(string $rgba8, int $width, int $height, array $damage): void { $this->pixels[] = [$rgba8, $width, $height, $damage]; }

    protected function applyAddress(int $address, int $width, int $height, int $stride, array $damage): void { $this->addresses[] = [$address, $width, $height, $stride, $damage]; }

    protected function applyTitle(string $title): void { $this->log[] = "title:{$title}"; }

    protected function applyVisible(bool $visible): void { $this->log[] = 'visible:'.(int) $visible; }

    protected function applyMode(WindowMode $mode, Display|DisplayMode|null $on): void
    {
        $target = match (true) {
            $on instanceof Display => ":display-{$on->id}",
            $on instanceof DisplayMode => ":{$on->width}x{$on->height}@{$on->refreshRate}",
            default => '',
        };
        $this->log[] = "mode:{$mode->value}{$target}";
        $this->modeChanged($mode);                                      // the native callback, synchronous here
    }

    protected function applyResize(int $width, int $height): void
    {
        $this->log[] = "resize:{$width}x{$height}";
        $this->measures = [$width, $height];
        $this->resized($width, $height);                              // the native resize callback, synchronous here
    }

    protected function applyDisplay(Display $display): void { $this->log[] = "display:{$display->id}"; $this->on_display = $display->id; }

    protected function applyLimits(int $minWidth, int $minHeight, int $maxWidth, int $maxHeight): void { $this->log[] = "limits:{$minWidth}x{$minHeight}-{$maxWidth}x{$maxHeight}"; }

    protected function applyAspectRatio(float $min, float $max): void { $this->log[] = "aspect:{$min}-{$max}"; }

    protected function applyResizable(bool $resizable): void { $this->log[] = 'resizable:'.(int) $resizable; }

    protected function applyBorderless(bool $borderless): void { $this->log[] = 'borderless:'.(int) $borderless; }

    protected function applyVsync(VSync $vsync): void { $this->log[] = "vsync:{$vsync->value}"; }

    protected function applyPosition(int $x, int $y): void { $this->log[] = "move:{$x},{$y}"; $this->nativeMoved($x, $y); }

    protected function applyAlwaysOnTop(bool $onTop): void { $this->log[] = 'on-top:'.(int) $onTop; }

    protected function applyFocusable(bool $focusable): void { $this->log[] = 'focusable:'.(int) $focusable; }

    protected function applyOpacity(float $opacity): void { $this->log[] = "opacity:{$opacity}"; }

    protected function applyKeepAwake(bool $awake): void { $this->log[] = 'awake:'.(int) $awake; }

    protected function applyAttention(): void { $this->log[] = 'attention'; }

    protected function applyIcon(string $rgba8, int $width, int $height): void { $this->log[] = "icon:{$width}x{$height}"; }

    protected function applyHitTest(?Closure $test): void { $this->log[] = 'hit-test:'.($test === null ? 'off' : 'on'); }

    /** What the toolkit's press handler asks. */
    public function press(int $x, int $y): mixed { return ($this->hit_test)($x, $y); }

    protected function destroyNative(): void { $this->log[] = 'destroy'; }

    protected function forget(): void { $this->stager->forget($this->name); }
}

/** A staged window whose stager lists a capability and leaves its apply hook alone. */
final class ForgetfulStagedWindow extends StagedWindow
{
    public function __construct(FakeSession $session)
    {
        parent::__construct('forgetful', $session, StagedWindow::options([], 'forgetful'));
    }

    public function backend(): string { return 'forgetful/test'; }

    public function isKey(): bool { return false; }

    public function surfaces(): array { return []; }

    protected function nativeCapabilities(): array { return [WindowCapability::Opacity]; }

    protected function nativePosition(): array { return [0, 0]; }

    protected function nativeSafeArea(): Region { return new Region(0, 0, 1, 1); }

    protected function nativeDisplay(): Display { return fakeDisplays()[0]; }

    protected function nativeDisplayModes(): array { return []; }

    protected function nativeHdr(): ?Hdr { return null; }

    protected function nativeSize(): array { return [1, 1]; }

    protected function nativeScale(): float { return 1.0; }

    protected function applyPixels(string $rgba8, int $width, int $height, array $damage): void {}

    protected function applyAddress(int $address, int $width, int $height, int $stride, array $damage): void {}

    protected function applyTitle(string $title): void {}

    protected function applyVisible(bool $visible): void {}

    protected function applyMode(WindowMode $mode, Display|DisplayMode|null $on): void {}

    protected function applyResize(int $width, int $height): void {}

    protected function applyDisplay(Display $display): void {}

    protected function applyLimits(int $minWidth, int $minHeight, int $maxWidth, int $maxHeight): void {}

    protected function applyAspectRatio(float $min, float $max): void {}

    protected function applyResizable(bool $resizable): void {}

    protected function applyBorderless(bool $borderless): void {}

    protected function applyVsync(VSync $vsync): void {}

    protected function destroyNative(): void {}

    protected function forget(): void {}
}

final class FakeStager implements StagedWindowDriver
{
    /** @var array<string, FakeStagedWindow> */
    private array $windows = [];

    public function __construct(public readonly FakeSession $session) {}

    public function openStaged(string $name, int $width, int $height, array $options = []): StagedWindowContract
    {
        if (isset($this->windows[$name])) {
            throw new WindowException("A staged window named '{$name}' is already open.");
        }
        $options = StagedWindow::options($options, $name);
        $window = new FakeStagedWindow($name, $this->session, $width, $height, $options, $this);
        $this->windows[$name] = $window;
        $window->open($options);

        return $window;
    }

    public function hasStaged(string $name): bool { return isset($this->windows[$name]); }

    public function getStaged(string $name): ?StagedWindowContract { return $this->windows[$name] ?? null; }

    public function allStaged(): array { return $this->windows; }

    public function closeAllStaged(): void
    {
        foreach ($this->windows as $window) {
            $window->close();
        }
    }

    public function displays(): array { return fakeDisplays(); }

    public function primaryDisplay(): Display { return fakeDisplays()[0]; }

    public function forget(string $name): void { unset($this->windows[$name]); }
}
