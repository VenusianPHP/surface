<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\SurfaceKind;
use Surface\Contracts\Drawing\VSync;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Windows\DisplayMode;
use Surface\Contracts\Windows\Hdr;
use Surface\Contracts\Windows\HitArea;
use Surface\Contracts\Windows\Mail\WindowClosed;
use Surface\Contracts\Windows\Mail\WindowCloseRequested;
use Surface\Contracts\Windows\Mail\WindowDisplayChanged;
use Surface\Contracts\Windows\Mail\WindowExposed;
use Surface\Contracts\Windows\Mail\WindowFocusLost;
use Surface\Contracts\Windows\Mail\WindowFrameDue;
use Surface\Contracts\Windows\Mail\WindowModeChanged;
use Surface\Contracts\Windows\Mail\WindowMoved;
use Surface\Contracts\Windows\Mail\WindowOccluded;
use Surface\Contracts\Windows\Mail\WindowScaleChanged;
use Surface\Contracts\Windows\ScaleFilter;
use Surface\Contracts\Windows\ScaleFit;
use Surface\Contracts\Windows\WindowCapability;
use Surface\Contracts\Windows\WindowException;
use Surface\Contracts\Windows\WindowMode;
use Surface\Windows\StagedWindow;
use Surface\Windows\StagedWindowManager;
use Venusian\Surface\Tests\Fixtures\FakeSession;

/*
 * What a game engine asks of its window: modes, displays, geometry, style,
 * presentation, power, attention, hit tests, and the mail that reports them.
 * FakeStagedWindow has every capability unless a test takes them away; its
 * native callbacks run synchronously.
 */

beforeEach(fn () => FakeStagedWindow::$capabilities = WindowCapability::cases());
afterEach(function (): void {
    FakeStagedWindow::$capabilities = [];
    StagedWindow::resolveFramebuffersUsing(null);
});

function stagedWith(array $options = [], ?FakeStager $stager = null): FakeStagedWindow
{
    $window = ($stager ?? stager())->openStaged('game', 320, 240, $options);
    $window->log = [];

    return $window;
}

it('names its backend and lists its capabilities', function (): void {
    FakeStagedWindow::$capabilities = [WindowCapability::Opacity];
    $window = stagedWith();

    expect($window->backend())->toBe('fake/test')
        ->and($window->capabilities())->toBe([WindowCapability::Opacity])
        ->and($window->supports(WindowCapability::Opacity))->toBeTrue()
        ->and($window->supports(WindowCapability::Position))->toBeFalse();
});

it('refuses each call whose capability the backend lacks, naming the backend', function (): void {
    FakeStagedWindow::$capabilities = [];
    $window = stagedWith();
    $mode = new DisplayMode(1, 1280, 800, 2.0, 60.0);

    expect(fn () => $window->move(10, 20))->toThrow(WindowException::class, "Staged window 'game' cannot move to 10,20: the fake/test backend does not support it.")
        ->and(fn () => $window->moveToDisplay(fakeDisplays()[1]))->toThrow(WindowException::class, 'cannot move to display 2')
        ->and(fn () => $window->setAlwaysOnTop(true))->toThrow(WindowException::class, 'cannot stay on top')
        ->and(fn () => $window->setFocusable(false))->toThrow(WindowException::class, 'cannot refuse focus')
        ->and(fn () => $window->setOpacity(0.5))->toThrow(WindowException::class, 'cannot set its opacity')
        ->and(fn () => $window->keepAwake(true))->toThrow(WindowException::class, 'cannot keep the display awake')
        ->and(fn () => $window->requestAttention())->toThrow(WindowException::class, 'cannot request attention')
        ->and(fn () => $window->setIcon("\0\0\0\0", 1, 1))->toThrow(WindowException::class, 'cannot set an icon')
        ->and(fn () => $window->hitTest(null))->toThrow(WindowException::class, 'cannot take a hit test')
        ->and(fn () => $window->setMode(WindowMode::Exclusive, $mode))->toThrow(WindowException::class, 'cannot go exclusive fullscreen')
        ->and($window->log)->toBe([]);
});

it('blames a stager that lists a capability without applying it', function (): void {
    expect(fn () => (new ForgetfulStagedWindow(new FakeSession()))->setOpacity(0.5))
        ->toThrow(WindowException::class, 'ForgetfulStagedWindow lists the opacity capability but does not override its apply hook.');
});

it('validates the opening options it grew', function (array $options, string $message): void {
    expect(fn () => stager()->openStaged('game', 1, 1, $options))->toThrow(WindowException::class, $message);
})->with([
    'x alone' => [['x' => 1], "Staged window 'game' takes options 'x' and 'y' together."],
    'x and display' => [['x' => 1, 'y' => 2, 'display' => fakeDisplays()[1]], "takes 'x' and 'y' or 'display', not both."],
    'exclusive bare' => [['mode' => WindowMode::Exclusive], "takes 'display_mode' with mode Exclusive, and Exclusive needs one."],
    'stray display mode' => [['display_mode' => new DisplayMode(1, 1, 1, 1.0, 60.0)], "takes 'display_mode' with mode Exclusive"],
    'mismatched display' => [['mode' => WindowMode::Exclusive, 'display' => fakeDisplays()[1], 'display_mode' => new DisplayMode(1, 1, 1, 1.0, 60.0)], "option 'display_mode' belongs to display 1, not 'display' 2."],
    'wrong enum' => [['mode' => 'fullscreen'], "option 'mode' is a Surface\\Contracts\\Windows\\WindowMode, got string."],
    'wrong vsync' => [['vsync' => true], "option 'vsync' is a Surface\\Contracts\\Drawing\\VSync, got bool."],
]);

it('takes the creation-time style options and reports them', function (): void {
    $window = stagedWith(['borderless' => true, 'always_on_top' => true, 'focusable' => false, 'transparent' => true, 'vsync' => VSync::Mailbox]);

    expect($window->isBorderless())->toBeTrue()
        ->and($window->isAlwaysOnTop())->toBeTrue()
        ->and($window->isFocusable())->toBeFalse()
        ->and($window->isTransparent())->toBeTrue()
        ->and($window->vsync())->toBe(VSync::Mailbox);
});

it('opens in exclusive fullscreen and on a display in borderless fullscreen', function (): void {
    $mode = new DisplayMode(1, 1280, 800, 2.0, 60.0);
    $exclusive = stager()->openStaged('a', 320, 240, ['mode' => WindowMode::Exclusive, 'display_mode' => $mode]);
    $covering = stager()->openStaged('b', 320, 240, ['mode' => WindowMode::Fullscreen, 'display' => fakeDisplays()[1]]);

    expect($exclusive->log)->toBe(['title:a', 'vsync:on', 'mode:exclusive:1280x800@60', 'visible:1'])
        ->and($exclusive->exclusiveMode())->toBe($mode)
        ->and($covering->log)->toContain('mode:fullscreen:display-2');
});

it('destroys the native window and frees the name when an opening option needs what the backend lacks', function (): void {
    FakeStagedWindow::$capabilities = [];
    $stager = stager();

    expect(fn () => $stager->openStaged('game', 1, 1, ['x' => 5, 'y' => 6]))
        ->toThrow(WindowException::class, "Staged window 'game' cannot open at 5,6: the fake/test backend does not support it.")
        ->and($stager->hasStaged('game'))->toBeFalse()
        ->and($stager->session->outbox())->toBe([])
        ->and(fn () => $stager->openStaged('game', 1, 1, ['mode' => WindowMode::Exclusive, 'display_mode' => new DisplayMode(1, 1, 1, 1.0, 60.0)]))
        ->toThrow(WindowException::class, 'cannot open in exclusive fullscreen')
        ->and(fn () => $stager->openStaged('game', 1, 1, ['display' => fakeDisplays()[1]]))
        ->toThrow(WindowException::class, 'cannot open on display 2: the fake/test backend does not support it.')
        ->and($stager->openStaged('covering', 1, 1, ['display' => fakeDisplays()[1], 'mode' => WindowMode::Fullscreen])->isOpen())->toBeTrue();
});

it('changes mode once per change, posting what the native window entered', function (): void {
    $window = stagedWith();
    $first = new DisplayMode(1, 1440, 900, 2.0, 60.0);
    $second = new DisplayMode(1, 1280, 800, 2.0, 60.0);

    $window->setMode(WindowMode::Maximized)->setMode(WindowMode::Maximized)
        ->setMode(WindowMode::Exclusive, $first)->setMode(WindowMode::Exclusive, new DisplayMode(1, 1440, 900, 2.0, 60.0))
        ->setMode(WindowMode::Exclusive, $second);
    expect($window->exclusiveMode())->toBe($second);
    $window->setMode(WindowMode::Windowed);

    expect($window->log)->toBe(['mode:maximized', 'mode:exclusive:1440x900@60', 'mode:exclusive:1280x800@60', 'mode:windowed'])
        ->and($window->exclusiveMode())->toBeNull()
        ->and($window->session()->outbox())->toEqual([
            new WindowModeChanged('game', WindowMode::Maximized),
            new WindowModeChanged('game', WindowMode::Exclusive),
            new WindowModeChanged('game', WindowMode::Exclusive),
            new WindowModeChanged('game', WindowMode::Windowed),
        ]);
});

it('follows a mode the user chose', function (): void {
    $window = stagedWith();

    $window->nativeMode(WindowMode::Minimized);

    expect($window->mode())->toBe(WindowMode::Minimized)
        ->and($window->session()->outbox())->toEqual([new WindowModeChanged('game', WindowMode::Minimized)]);
});

it('refuses a mode target that does not fit the mode', function (): void {
    $window = stagedWith();

    expect(fn () => $window->setMode(WindowMode::Exclusive))->toThrow(WindowException::class, "Staged window 'game' goes exclusive fullscreen at a DisplayMode, got null.")
        ->and(fn () => $window->setMode(WindowMode::Exclusive, fakeDisplays()[0]))->toThrow(WindowException::class, 'at a DisplayMode, got Surface\Contracts\Windows\Display.')
        ->and(fn () => $window->setMode(WindowMode::Fullscreen, new DisplayMode(1, 1, 1, 1.0, 60.0)))->toThrow(WindowException::class, 'covers a Display in borderless fullscreen; a DisplayMode is for Exclusive.')
        ->and(fn () => $window->setMode(WindowMode::Maximized, fakeDisplays()[0]))->toThrow(WindowException::class, 'takes no display for mode maximized.');
});

it('moves to another display by mode', function (): void {
    $window = stagedWith();
    $external = fakeDisplays()[1];

    $window->moveToDisplay($external);
    expect($window->display()->id)->toBe(2);

    $window->setMode(WindowMode::Fullscreen)->moveToDisplay(fakeDisplays()[0]);
    expect($window->log)->toBe(['display:2', 'mode:fullscreen', 'mode:fullscreen:display-1']);

    $window->setMode(WindowMode::Exclusive, new DisplayMode(1, 1280, 800, 2.0, 60.0));
    expect(fn () => $window->moveToDisplay($external))
        ->toThrow(WindowException::class, "Staged window 'game' is exclusive fullscreen: setMode(WindowMode::Exclusive, a mode of display 2) moves it.");
});

it('reports its display, the display modes, HDR, the safe area and its position', function (): void {
    $window = stagedWith();

    expect($window->display()->name)->toBe('Built-in')
        ->and($window->displayModes())->toHaveCount(2)
        ->and($window->displayModes()[1]->width)->toBe(1280)
        ->and($window->hdr()?->headroom)->toBe(4.0)
        ->and($window->safeArea())->toEqual(new Region(0, 16, 320, 224))
        ->and($window->position())->toBe([100, 80])
        ->and($window->move(10, 20)->position())->toBe([10, 20]);

    $window->nativeScreenMove(2, 1.0);
    expect($window->hdr())->toBeNull();
});

it('lists the displays through the manager', function (): void {
    $manager = new StagedWindowManager(stagersNamed(['one' => stager()], 'none'), 'one');

    expect($manager->displays())->toHaveCount(2)
        ->and($manager->primaryDisplay()->name)->toBe('Built-in')
        ->and($manager->displays()[1]->bounds->x)->toBe(1440);
});

it('takes size limits and an aspect ratio, refusing nonsense', function (): void {
    $window = stagedWith();

    $window->setLimits(100, 80, 800, 600)->setAspectRatio(1.0, 2.0)->setLimits(100, 80);

    expect($window->limits())->toBe([100, 80, 0, 0])
        ->and($window->aspectRatio())->toBe([1.0, 2.0])
        ->and($window->log)->toBe(['limits:100x80-800x600', 'aspect:1-2', 'limits:100x80-0x0'])
        ->and(fn () => $window->setLimits(-1, 0))->toThrow(WindowException::class, "Staged window 'game' size limits are 0 or more, got -1x0 to 0x0.")
        ->and(fn () => $window->setLimits(900, 80, 800, 600))->toThrow(WindowException::class, 'minimum size 900x80 is past its maximum 800x600.')
        ->and(fn () => $window->setAspectRatio(2.0, 1.0))->toThrow(WindowException::class, 'aspect ratio runs from 0.0 up, minimum first, got 2 to 1.');
});

it('toggles its style', function (): void {
    $window = stagedWith();

    $window->setResizable(false)->setBorderless(true)->setAlwaysOnTop(true)->setFocusable(false)->setOpacity(0.25);

    expect([$window->isResizable(), $window->isBorderless(), $window->isAlwaysOnTop(), $window->isFocusable(), $window->opacity()])
        ->toBe([false, true, true, false, 0.25])
        ->and($window->log)->toBe(['resizable:0', 'borderless:1', 'on-top:1', 'focusable:0', 'opacity:0.25'])
        ->and(fn () => $window->setOpacity(1.5))->toThrow(WindowException::class, 'opacity runs 0.0 to 1.0, got 1.5.');
});

it('applies vsync to its own present and hands it to the surface it lends, following changes', function (): void {
    $window = stagedWith(['vsync' => VSync::Adaptive]);
    $window->lends = [SurfaceKind::METAL_LAYER];
    $surface = $window->lend(SurfaceKind::METAL_LAYER, new FakeBorrower(new FakeGLFramebuffer(640, 480)));
    $heard = [];
    $surface->onVsync(function (VSync $vsync) use (&$heard): void { $heard[] = $vsync; });

    $window->setVsync(VSync::Off)->setVsync(VSync::Off);
    $window->reclaim();
    $window->setVsync(VSync::On);

    expect($surface->vsync())->toBe(VSync::Off)
        ->and($heard)->toBe([VSync::Off])
        ->and($window->log)->toContain('vsync:off')
        ->and(array_values(array_filter($window->log, fn (string $line): bool => str_starts_with($line, 'vsync'))))->toBe(['vsync:off', 'vsync:off', 'vsync:on']);
});

it('places a framebuffer of another size by the scaling fit', function (ScaleFit $fit, int $width, int $height, Region $rect): void {
    $window = stagedWith();                                           // 640x480 pixels
    $window->setScaling(ScaleFilter::Nearest, $fit);

    expect($window->presentRect($width, $height))->toEqual($rect)
        ->and($window->scaling())->toBe([ScaleFilter::Nearest, $fit]);
})->with([
    'stretch' => [ScaleFit::Stretch, 320, 200, new Region(0, 0, 640, 480)],
    'letterbox wide' => [ScaleFit::Letterbox, 320, 200, new Region(0, 40, 640, 400)],
    'letterbox tall' => [ScaleFit::Letterbox, 100, 200, new Region(200, 0, 240, 480)],
    'integer once' => [ScaleFit::Integer, 400, 300, new Region(120, 90, 400, 300)],
    'integer twice' => [ScaleFit::Integer, 300, 200, new Region(20, 40, 600, 400)],
    'integer doubled' => [ScaleFit::Integer, 160, 100, new Region(0, 40, 640, 400)],
    'integer too large' => [ScaleFit::Integer, 1280, 800, new Region(0, 40, 640, 400)],
]);

it('shows the whole frame again after the scaling changes', function (): void {
    $window = stagedWith();
    $window->framebuffer();
    $window->present()->present();
    expect($window->pixels)->toHaveCount(1);

    $window->setScaling(ScaleFilter::Nearest, ScaleFit::Integer)->present();

    expect($window->pixels)->toHaveCount(2);
});

it('keeps the display awake, asks for attention and takes an icon', function (): void {
    $window = stagedWith();

    $window->keepAwake(true)->requestAttention()->setIcon(str_repeat("\xff", 16), 2, 2);

    expect($window->isKeptAwake())->toBeTrue()
        ->and($window->log)->toBe(['awake:1', 'attention', 'icon:2x2'])
        ->and(fn () => $window->setIcon('abc', 2, 2))->toThrow(WindowException::class, "Staged window 'game' icon of 2x2 is 16 RGBA8 bytes, got 3.");
});

it('asks its hit test where a press lands', function (): void {
    $window = stagedWith();

    $window->hitTest(fn (int $x, int $y): HitArea => $y < 30 ? HitArea::Draggable : HitArea::Normal);

    expect($window->press(10, 5))->toBe(HitArea::Draggable)
        ->and($window->press(10, 50))->toBe(HitArea::Normal)
        ->and($window->hitTest(null)->log)->toBe(['hit-test:on', 'hit-test:off']);
});

it('posts focus loss, occlusion, display and scale changes in order, and moves and frames latest-wins', function (): void {
    $window = stagedWith();
    $session = $window->session();

    $window->blur();
    $window->nativeOccluded(true);
    $window->nativeOccluded(true);
    expect($window->isOccluded())->toBeTrue();
    $window->nativeOccluded(false);
    $window->nativeOccluded(false);
    $window->nativeScreenMove(2, 1.0);
    $window->nativeScreenMove(1, 1.0);
    $window->nativeMoved(5, 5);
    $window->nativeMoved(6, 7);
    $window->nativeFrame(1.0, 1.016);
    $window->nativeFrame(1.016, 1.033);

    expect($session->outbox())->toEqual([
        new WindowFocusLost('game'),
        new WindowOccluded('game'),
        new WindowExposed('game'),
        new WindowDisplayChanged('game', 2),
        new WindowScaleChanged('game', 1.0),
        new WindowDisplayChanged('game', 1),
    ]);
    $session->flushLatest();
    expect(array_slice($session->outbox(), 6))->toEqual([new WindowMoved('game', 6, 7), new WindowFrameDue('game', 1.016, 1.033)]);
});

it('closes on the close box, or asks first when opened with confirm_close', function (): void {
    $plain = stagedWith();
    $plain->nativeCloseBox();

    $asking = stager()->openStaged('asking', 1, 1, ['confirm_close' => true]);
    $asking->nativeCloseBox();

    expect($plain->isOpen())->toBeFalse()
        ->and($plain->session()->outbox())->toEqual([new WindowClosed('game')])
        ->and($asking->isOpen())->toBeTrue()
        ->and($asking->session()->outbox())->toEqual([new WindowCloseRequested('asking')]);

    $asking->close();
    expect($asking->session()->outbox())->toEqual([new WindowCloseRequested('asking'), new WindowClosed('asking')]);
});

it('drops mail from native callbacks that arrive after it closed', function (): void {
    $window = stagedWith();
    $window->close();
    $session = $window->session();

    $window->blur();
    $window->nativeMode(WindowMode::Maximized);
    $window->nativeOccluded(true);
    $window->nativeScreenMove(2, 1.0);
    $window->nativeMoved(1, 1);
    $window->nativeFrame(0.0, 0.0);
    $window->nativeCloseBox();
    $session->flushLatest();

    expect($session->outbox())->toEqual([new WindowClosed('game')]);
});

it('hands its scaling and its HDR state to the surface it lends, following changes', function (): void {
    $window = stagedWith();                                           // 640x480 pixels, on the HDR built-in display
    $window->setScaling(ScaleFilter::Nearest, ScaleFit::Integer);
    $window->lends = [SurfaceKind::METAL_LAYER];
    $surface = $window->lend(SurfaceKind::METAL_LAYER, new FakeBorrower(new FakeGLFramebuffer(320, 240)));

    $lent = $surface->scaling();
    $window->setScaling(ScaleFilter::Linear, ScaleFit::Letterbox);

    expect($lent)->toBe([ScaleFilter::Nearest, ScaleFit::Integer])
        ->and($surface->scaling())->toBe([ScaleFilter::Linear, ScaleFit::Letterbox])
        ->and($surface->presentRect(320, 200))->toEqual($window->presentRect(320, 200))
        ->and($surface->hdr())->toEqual(new Hdr(true, 1.0, 4.0));
});
