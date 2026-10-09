<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\SurfaceKind;
use Surface\Contracts\Framebuffers\DamageTrackingFramebuffer;
use Surface\Contracts\Windows\Mail\WindowClosed;
use Surface\Contracts\Windows\Mail\WindowFocused;
use Surface\Contracts\Windows\Mail\WindowResized;
use Surface\Contracts\Windows\StagedWindow as StagedWindowContract;
use Surface\Contracts\Windows\WindowException;
use Surface\Contracts\Windows\WindowMode;
use Surface\Windows\StagedWindow;
use Venusian\Surface\Tests\Fixtures\FakeSession;

/*
 * A staged window is one output filling a window: no primitives. FakeStager
 * opens FakeStagedWindows at scale 2 that log every native call and lend
 * what $lends lists. Mail goes through a FakeSession that is not on a loop,
 * so posted mail is read from its outbox and latest mail stays pending until
 * flushLatest().
 */

function stager(): FakeStager
{
    return new FakeStager(new FakeSession());
}

function staged(array $options = []): FakeStagedWindow
{
    return stager()->openStaged('game', 320, 240, $options);
}

afterEach(fn () => StagedWindow::resolveFramebuffersUsing(null));

it('opens with its size, a title of its name, visible, windowed and resizable', function (): void {
    $window = staged();

    expect($window)->toBeInstanceOf(StagedWindowContract::class)
        ->and($window->name())->toBe('game')
        ->and($window->title())->toBe('game')
        ->and($window->size())->toBe([320, 240])
        ->and($window->scale())->toBe(2.0)
        ->and($window->pixelSize())->toBe([640, 480])
        ->and($window->isOpen())->toBeTrue()
        ->and($window->isVisible())->toBeTrue()
        ->and($window->mode())->toBe(WindowMode::Windowed)
        ->and($window->isResizable())->toBeTrue()
        ->and($window->log)->toBe(['title:game', 'vsync:on', 'visible:1']);
});

it('takes a title, hidden, fullscreen and fixed-size options', function (): void {
    $window = staged(['title' => 'Doom', 'visible' => false, 'mode' => WindowMode::Fullscreen, 'resizable' => false]);

    expect($window->title())->toBe('Doom')
        ->and($window->isVisible())->toBeFalse()
        ->and($window->mode())->toBe(WindowMode::Fullscreen)
        ->and($window->isResizable())->toBeFalse()
        ->and($window->log)->toBe(['title:Doom', 'vsync:on', 'mode:fullscreen']);
});

it('refuses an option it does not know, naming it and the known ones', function (): void {
    staged(['titel' => 'x', 'fullscreen' => true]);
})->throws(WindowException::class, "Staged window 'game' takes no option 'titel', 'fullscreen' (it takes: title, visible, resizable, mode, display, display_mode, x, y, borderless, always_on_top, focusable, transparent, confirm_close, vsync).");

it('refuses an option of the wrong type', function (): void {
    staged(['resizable' => 'yes']);
})->throws(WindowException::class, "Staged window 'game' option 'resizable' is a bool, got string.");

it('refuses a name already open', function (): void {
    $stager = stager();
    $stager->openStaged('game', 1, 1);
    $stager->openStaged('game', 1, 1);
})->throws(WindowException::class, "A staged window named 'game' is already open.");

it('sets its title, shows and hides, goes fullscreen and back, resizes', function (): void {
    $window = staged();
    $window->log = [];

    $window->setTitle('Doom')->hide()->show()->setMode(WindowMode::Fullscreen)->setMode(WindowMode::Fullscreen)->setMode(WindowMode::Windowed)->resize(640, 400);

    expect($window->title())->toBe('Doom')
        ->and($window->isVisible())->toBeTrue()
        ->and($window->mode())->toBe(WindowMode::Windowed)
        ->and($window->size())->toBe([640, 400])
        ->and($window->log)->toBe(['title:Doom', 'visible:0', 'visible:1', 'mode:fullscreen', 'mode:windowed', 'resize:640x400']);
});

it('posts a resize as latest mail in points, and a focus change in order', function (): void {
    $window = staged();
    $session = $window->session();

    $window->resize(400, 300);
    $window->resize(500, 300);
    $window->key = true;
    $window->focus();

    expect($session->outbox())->toEqual([new WindowFocused('game')])
        ->and($window->isKey())->toBeTrue();

    $session->flushLatest();
    expect($session->outbox())->toEqual([new WindowFocused('game'), new WindowResized('game', 500, 300)]);
});

it('closes: reclaims, destroys the native window, posts the pending resize then WindowClosed, and is gone from its stager', function (): void {
    $stager = stager();
    $window = $stager->openStaged('game', 320, 240);
    $session = $window->session();
    $window->resize(400, 300);
    $window->log = [];

    $window->close();
    $window->close();

    expect($window->isOpen())->toBeFalse()
        ->and($window->log)->toBe(['destroy'])
        ->and($session->outbox())->toEqual([new WindowResized('game', 400, 300), new WindowClosed('game')])
        ->and($stager->hasStaged('game'))->toBeFalse()
        ->and($stager->getStaged('game'))->toBeNull()
        ->and($stager->allStaged())->toBe([]);

    $session->flushLatest();
    expect($session->outbox())->toHaveCount(2);
});

it('a native close reclaims the lent surface first and posts WindowClosed once', function (): void {
    $window = staged();
    $window->lends = [SurfaceKind::GL_CONTEXT];
    $borrower = new FakeBorrower(new FakeGLFramebuffer(640, 480));
    $surface = $window->lend(SurfaceKind::GL_CONTEXT, $borrower);
    $window->log = [];

    $window->nativeClosed();                                        // the user hit the close box

    expect($surface->released())->toBeTrue()
        ->and($window->log)->toBe(['unsurface:gl-context'])
        ->and($window->lent())->toBeNull()
        ->and($window->isOpen())->toBeFalse()
        ->and($window->session()->outbox())->toEqual([new WindowClosed('game')]);
});

it('drops a native resize or focus that arrives after it closed', function (): void {
    $window = staged();
    $window->close();
    $session = $window->session();

    $window->nativeResized(800, 600);
    $window->focus();
    $session->flushLatest();

    expect($session->outbox())->toEqual([new WindowClosed('game')]);
});

it('refuses everything once closed', function (): void {
    $window = staged();
    $window->close();

    expect(fn () => $window->setTitle('x'))->toThrow(WindowException::class, "Staged window 'game' was closed.")
        ->and(fn () => $window->show())->toThrow(WindowException::class, 'was closed')
        ->and(fn () => $window->setMode(WindowMode::Fullscreen))->toThrow(WindowException::class, 'was closed')
        ->and(fn () => $window->setVsync(\Surface\Contracts\Drawing\VSync::Off))->toThrow(WindowException::class, 'was closed')
        ->and(fn () => $window->display())->toThrow(WindowException::class, 'was closed')
        ->and(fn () => $window->resize(1, 1))->toThrow(WindowException::class, 'was closed')
        ->and(fn () => $window->framebuffer())->toThrow(WindowException::class, 'was closed')
        ->and(fn () => $window->present())->toThrow(WindowException::class, 'was closed')
        ->and(fn () => $window->pixelSize())->toThrow(WindowException::class, 'was closed')
        ->and(fn () => $window->lend(SurfaceKind::GL_CONTEXT, new FakeBorrower(new FakeGLFramebuffer(1, 1))))->toThrow(WindowException::class, 'was closed');
});

it('hands out a framebuffer before it is shown, its pixel size, and presents it', function (): void {
    $window = staged(['visible' => false]);

    $buffer = $window->framebuffer();
    $buffer->setPixel(0, 0, 0x11223344);
    $window->present();

    expect([$buffer->viewportWidth(), $buffer->viewportHeight()])->toBe([640, 480])
        ->and($buffer)->toBeInstanceOf(DamageTrackingFramebuffer::class)
        ->and($window->pixels)->toHaveCount(1)
        ->and($window->pixels[0][1])->toBe(640)
        ->and(substr($window->pixels[0][0], 0, 4))->toBe("\x11\x22\x33\x44");
});

it('mints a new framebuffer after a resize, and keeps the old one until asked', function (): void {
    $window = staged();
    $before = $window->framebuffer();

    $window->resize(100, 50);

    expect($window->boundFramebuffer())->toBe($before);
    $after = $window->framebuffer();
    expect($after)->not->toBe($before)
        ->and([$after->viewportWidth(), $after->viewportHeight()])->toBe([200, 100]);
});

it('names itself in the messages the shared machinery throws', function (): void {
    $window = staged();

    expect(fn () => $window->present())->toThrow(WindowException::class, "Staged window 'game' has no framebuffer: call framebuffer() first.")
        ->and(fn () => $window->framebuffer('paged'))->toThrow(WindowException::class, "A staged window framebuffer is 'full', 'dirty' or 'ring', got 'paged'.")
        ->and(fn () => $window->lend(SurfaceKind::METAL_LAYER, new FakeBorrower(new FakeGLFramebuffer(1, 1))))
            ->toThrow(WindowException::class, "Staged window 'game' lends no metal-layer surface (it lends: none).");

    $window->lends = [SurfaceKind::GL_CONTEXT];
    $window->lend(SurfaceKind::GL_CONTEXT, new FakeBorrower(new FakeGLFramebuffer(640, 480)));
    expect(fn () => $window->framebuffer())
        ->toThrow(WindowException::class, "Staged window 'game' has lent its surface: its pixels are the borrower's. reclaim() it to draw into the staged window's own framebuffer.");
});

it('lends, presents through the borrower at its pixel size, and reclaims in release-then-remove order', function (): void {
    $window = staged();
    $window->lends = [SurfaceKind::VULKAN_SURFACE];
    $borrower = new FakeBorrower(new FakeGLFramebuffer(640, 480), ['instance' => 0xABC]);

    $surface = $window->lend(SurfaceKind::VULKAN_SURFACE, $borrower);
    $window->present();
    $window->resize(100, 50);

    expect($surface->handle('surface'))->toBe(0xC0FFEE)
        ->and($surface->size())->toBe([200, 100])
        ->and($window->log)->toContain('surface:vulkan-surface:{"instance":2748}')
        ->and($borrower->presented)->toHaveCount(1)
        ->and($window->boundFramebuffer())->toBe($borrower->framebuffer());

    $order = [];
    $surface->onRelease(function () use (&$order): void { $order[] = 'released'; });
    $window->reclaim();
    $order[] = array_pop($window->log);

    expect($order)->toBe(['released', 'unsurface:vulkan-surface'])
        ->and($window->lent())->toBeNull()
        ->and($window->boundFramebuffer())->toBeNull();
});

it('closes every window from its stager', function (): void {
    $stager = stager();
    $a = $stager->openStaged('a', 1, 1);
    $b = $stager->openStaged('b', 1, 1);

    $stager->closeAllStaged();

    expect($a->isOpen())->toBeFalse()
        ->and($b->isOpen())->toBeFalse()
        ->and($stager->allStaged())->toBe([]);
});
