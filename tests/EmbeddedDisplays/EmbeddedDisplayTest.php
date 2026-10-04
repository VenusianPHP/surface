<?php

use GeneralPurposeIO\Contracts\IntegratedCircuits\RefreshMode;
use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplayException;
use Surface\Contracts\EmbeddedDisplays\Mail\DisplayFaulted;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\BitOrder;
use Surface\Contracts\Framebuffers\DamageTrackingFramebuffer;
use Surface\Contracts\Framebuffers\Endianness;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\PageAxis;
use Surface\Contracts\Framebuffers\PagedFramebuffer;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Framebuffers\RingFramebuffer;
use Surface\Contracts\Framebuffers\ScanDirection;
use Surface\Contracts\Framebuffers\ePaperFramebuffer;
use Surface\EmbeddedDisplays\EmbeddedDisplay;
use Surface\Framebuffers\PixelMapper;

function panelDisplay(FakePanel $panel, array $kinds = [], ?Closure $post = null, ?Closure $on_close = null): EmbeddedDisplay
{
    return new EmbeddedDisplay('panel', $panel, framebuffers(), $kinds + ['refreshing' => 'epaper', 'addressable' => 'dirty', 'whole' => 'full'], $on_close, $post);
}

function panelRgb565(): FormatSpec
{
    return new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B16, endianness: Endianness::MSB);
}

/** The SSD1306's format. */
function panelPages1(): FormatSpec
{
    return new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1, ScanDirection::TOP_TO_BOTTOM, BitOrder::LSB_FIRST, page_axis: PageAxis::VERTICAL);
}

/** The SSD1680 black/white format. */
function panelMono1(): FormatSpec
{
    return new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1, ScanDirection::TOP_TO_BOTTOM, BitOrder::MSB_FIRST);
}

function inkOf(Framebuffer $framebuffer): int
{
    return PixelMapper::for($framebuffer->hostFormat())->fromRgba8(255, 255, 255);
}

describe('the framebuffer a display hands out', function () {
    it('is picked by how the panel behaves', function (FakePanel $panel, string $contract) {
        expect(panelDisplay($panel)->framebuffer())->toBeInstanceOf($contract);
    })->with([
        'ePaper' => fn () => [new FakeInkPanel(16, 8, panelMono1()), ePaperFramebuffer::class],
        'region writes' => fn () => [new FakeWindowPanel(16, 16, panelRgb565()), DamageTrackingFramebuffer::class],
    ]);

    it('is a full framebuffer for a panel that takes whole frames', function () {
        $framebuffer = panelDisplay(new FakeWholePanel(4, 2, panelRgb565()))->framebuffer();

        expect($framebuffer)->not->toBeInstanceOf(DamageTrackingFramebuffer::class)
            ->and($framebuffer)->not->toBeInstanceOf(ePaperFramebuffer::class)
            ->and($framebuffer)->not->toBeInstanceOf(RingFramebuffer::class);
    });

    it('follows the configured kind for a panel behaviour', function () {
        $framebuffer = panelDisplay(new FakeWindowPanel(16, 16, panelRgb565()), ['addressable' => 'full'])->framebuffer();

        expect($framebuffer)->not->toBeInstanceOf(DamageTrackingFramebuffer::class);
    });

    it('is the panel size in the panel format, and binds itself', function () {
        $display = panelDisplay(new FakeWindowPanel(16, 8, panelRgb565()));
        $framebuffer = $display->framebuffer();

        expect([$framebuffer->viewportWidth(), $framebuffer->viewportHeight()])->toBe([16, 8])
            ->and($framebuffer->hostFormat()->equals(panelRgb565()))->toBeTrue()
            ->and($display->boundFramebuffer())->toBe($framebuffer);
    });

    it('is the same one while the panel format holds, a new one after it changes', function () {
        $panel = new FakeWindowPanel(16, 16, panelPages1());
        $display = panelDisplay($panel);
        $first = $display->framebuffer();

        expect($display->framebuffer())->toBe($first);

        $panel->format = panelRgb565();
        $second = $display->framebuffer();

        expect($second)->not->toBe($first)
            ->and($second->hostFormat()->equals(panelRgb565()))->toBeTrue();
    });

    it('can be named paged or ring', function () {
        $display = panelDisplay(new FakeWindowPanel(16, 16, panelRgb565()));

        expect($display->framebuffer('paged', page_rows: 8))->toBeInstanceOf(PagedFramebuffer::class)
            ->and($display->framebuffer('ring', frames: 3))->toBeInstanceOf(RingFramebuffer::class)
            ->and($display->boundFramebuffer()->frames())->toBe(3);
    });

    it('needs page rows for a paged framebuffer', function () {
        panelDisplay(new FakeWindowPanel(16, 16, panelRgb565()))->framebuffer('paged');
    })->throws(EmbeddedDisplayException::class, "Embedded display 'panel' needs page_rows for a paged framebuffer.");

    it('is one of the five kinds', function () {
        panelDisplay(new FakeWindowPanel(16, 16, panelRgb565()))->framebuffer('blurry');
    })->throws(EmbeddedDisplayException::class, "got 'blurry'");
});

describe('present()', function () {
    it('sends the whole frame first, in the panel format', function () {
        $panel = new FakeWindowPanel(16, 16, panelRgb565());
        $display = panelDisplay($panel);
        $framebuffer = $display->framebuffer();
        $framebuffer->setPixel(1, 1, inkOf($framebuffer));

        $display->present();

        expect($panel->windows())->toBe([[0, 0, 16, 16]])
            ->and($panel->calls[0][5])->toBe($framebuffer->flush(panelRgb565(), true));
    });

    it('then sends only the damage to a panel that takes region writes', function () {
        $panel = new FakeWindowPanel(16, 16, panelRgb565());
        $display = panelDisplay($panel);
        $framebuffer = $display->framebuffer();
        $display->present();

        $framebuffer->setPixel(3, 5, inkOf($framebuffer));
        $display->present();

        expect($panel->windows())->toBe([[0, 0, 16, 16], [3, 5, 1, 1]])
            ->and($panel->calls[1][5])->toBe($framebuffer->flushRegion(new Region(3, 5, 1, 1), panelRgb565(), true));
    });

    it('sends nothing when nothing was drawn', function () {
        $panel = new FakeWindowPanel(16, 16, panelRgb565());
        $display = panelDisplay($panel);
        $display->framebuffer();
        $display->present()->present();

        expect($panel->windows())->toBe([[0, 0, 16, 16]]);
    });

    it('sends whole 8-row pages to a vertical-page panel', function () {
        $panel = new FakeWindowPanel(16, 16, panelPages1());
        $display = panelDisplay($panel);
        $framebuffer = $display->framebuffer();
        $display->present();

        $framebuffer->setPixel(3, 5, inkOf($framebuffer));
        $display->present();

        expect($panel->windows()[1])->toBe([0, 0, 16, 8]);
    });

    it('converts a bound framebuffer of another format to the panel format', function () {
        $panel = new FakeWindowPanel(16, 16, panelPages1());
        $display = panelDisplay($panel);
        $rgba = framebuffers()->driver()->dirty(FormatSpec::rgba8(), 16, 16);
        $native = framebuffers()->driver()->dirty(panelPages1(), 16, 16);
        foreach ([[1, 1], [9, 12]] as [$x, $y]) {
            $rgba->setPixel($x, $y, inkOf($rgba));
            $native->setPixel($x, $y, inkOf($native));
        }

        $display->bind($rgba)->present();

        expect($panel->calls[0][5])->toBe($native->flush(panelPages1(), true));
    });

    it('snaps a bound framebuffer damage to the panel pages', function () {
        $panel = new FakeWindowPanel(16, 16, panelPages1());
        $display = panelDisplay($panel);
        $rgba = framebuffers()->driver()->dirty(FormatSpec::rgba8(), 16, 16);
        $display->bind($rgba)->present();

        $rgba->setPixel(3, 5, inkOf($rgba));
        $display->present();

        expect($panel->windows()[1])->toBe([0, 0, 16, 8]);
    });

    it('snaps a bound framebuffer damage to whole bytes across a 1-bit horizontal panel', function () {
        $panel = new FakeWindowInkPanel(16, 8, panelMono1());
        $display = panelDisplay($panel);
        $rgba = framebuffers()->driver()->dirty(FormatSpec::rgba8(), 16, 8);
        $display->bind($rgba)->present();

        $rgba->setPixel(3, 2, inkOf($rgba));
        $display->present();

        expect($panel->windows()[1])->toBe([0, 2, 8, 1]);
    });

    it('refuses a framebuffer of another size', function () {
        panelDisplay(new FakeWindowPanel(16, 16, panelRgb565()))->bind(framebuffers()->driver()->full(panelRgb565(), 8, 8));
    })->throws(EmbeddedDisplayException::class, "Embedded display 'panel' is 16x16; a 8x8 framebuffer cannot be bound to it.");

    it('sends the whole frame after a bind', function () {
        $panel = new FakeWindowPanel(16, 16, panelRgb565());
        $display = panelDisplay($panel);
        $display->framebuffer();
        $display->present();

        $display->bind(framebuffers()->driver()->dirty(panelRgb565(), 16, 16))->present();

        expect($panel->windows())->toBe([[0, 0, 16, 16], [0, 0, 16, 16]]);
    });

    it('sends the whole frame for any damage to a panel that takes whole frames', function () {
        $panel = new FakeWholePanel(4, 2, panelRgb565());
        $display = panelDisplay($panel);
        $framebuffer = $display->bind(framebuffers()->driver()->dirty(panelRgb565(), 4, 2))->boundFramebuffer();
        $display->present()->present();

        $framebuffer->setPixel(0, 0, inkOf($framebuffer));
        $display->present();

        expect($panel->windows())->toBe([[0, 0, 4, 2], [0, 0, 4, 2]]);
    });

    it('refreshes an ePaper panel once after the whole frame, in the mode asked for', function () {
        $panel = new FakeInkPanel(16, 8, panelMono1());
        $display = panelDisplay($panel);
        $display->framebuffer();

        $display->present();
        $display->refreshMode(RefreshMode::PARTIAL)->present();

        expect($panel->verbs())->toBe(['transmit', 'refresh', 'transmit', 'refresh'])
            ->and($panel->calls[1][1])->toBe(RefreshMode::FULL)
            ->and($panel->calls[3][1])->toBe(RefreshMode::PARTIAL);
    });

    it('sends a ring only after it presents, its damage since the frame last sent', function () {
        $panel = new FakeWindowPanel(16, 16, panelRgb565());
        $display = panelDisplay($panel);
        $ring = $display->framebuffer('ring', frames: 3);
        $ring->setPixel(0, 0, inkOf($ring));
        $ring->present();
        $display->present()->present();

        $before = $ring->serial();
        $ring->repair();
        $ring->setPixel(7, 9, inkOf($ring));
        $ring->present();
        $display->present();

        $damage = $ring->damage($before);
        expect($panel->windows())->toBe([[0, 0, 16, 16], ...array_map(fn (Region $r): array => [$r->x, $r->y, $r->width, $r->height], $damage)])
            ->and($damage)->not->toBe([])
            ->and(end($panel->calls)[5])->toBe($ring->flushRegion($damage[0], panelRgb565(), true));
    });

    it('sends a paged framebuffer page by page and refreshes after the last', function () {
        $panel = new FakeWindowInkPanel(16, 16, panelMono1());
        $display = panelDisplay($panel);
        $paged = $display->framebuffer('paged', page_rows: 8);

        $paged->setPage(0);
        $display->present();
        $paged->setPage(1);
        $display->present();

        expect($panel->windows())->toBe([[0, 0, 16, 8], [0, 8, 16, 8]])
            ->and($panel->verbs())->toBe(['transmit', 'transmit', 'refresh'])
            ->and($panel->calls[1][5])->toBe($paged->flush(panelMono1(), true));
    });

    it('refuses a paged framebuffer on a panel that takes whole frames', function () {
        panelDisplay(new FakeInkPanel(16, 16, panelMono1()))->framebuffer('paged', page_rows: 8);
    })->throws(EmbeddedDisplayException::class, 'its panel takes whole frames only');

    it('refuses page rows the panel cannot address', function () {
        panelDisplay(new FakeWindowPanel(16, 16, panelPages1()))->framebuffer('paged', page_rows: 12);
    })->throws(EmbeddedDisplayException::class, 'needs page_rows in multiples of 8, got 12');

    it('needs a framebuffer', function () {
        panelDisplay(new FakeWindowPanel(16, 16, panelRgb565()))->present();
    })->throws(EmbeddedDisplayException::class, "Embedded display 'panel' has no framebuffer: call framebuffer() or bind() first.");
});

describe('show() and hide()', function () {
    it('switch the panel, skip presents while hidden, and send the whole frame after show', function () {
        $panel = new FakeWindowPanel(16, 16, panelRgb565());
        $display = panelDisplay($panel);
        $framebuffer = $display->framebuffer();
        $display->present();

        $display->hide();
        $framebuffer->setPixel(1, 1, inkOf($framebuffer));
        $display->present();
        $hidden = $display->isVisible();
        $display->show()->present();

        expect($hidden)->toBeFalse()
            ->and($display->isVisible())->toBeTrue()
            ->and($panel->verbs())->toBe(['transmit', 'display', 'display', 'transmit'])
            ->and($panel->calls[1][1])->toBeFalse()
            ->and($panel->calls[2][1])->toBeTrue()
            ->and($panel->windows()[1])->toBe([0, 0, 16, 16]);
    });

    it('throw on a panel that cannot switch', function () {
        $display = panelDisplay(new FakeInkPanel(16, 8, panelMono1()));

        expect($display->switchable())->toBeFalse();
        $display->hide();
    })->throws(EmbeddedDisplayException::class, 'cannot be switched on or off');
});

describe('a fault', function () {
    it('is latched, mailed once, and stops every later send', function () {
        $mail = [];
        $panel = new FakeWindowPanel(16, 16, panelRgb565());
        $display = panelDisplay($panel, post: function (object $m) use (&$mail): void {
            $mail[] = $m;
        });
        $framebuffer = $display->framebuffer();
        $panel->fail = new RuntimeException('bus gone');

        $display->present();
        $framebuffer->setPixel(1, 1, inkOf($framebuffer));
        $display->present();

        expect($display->faulted())->toBeTrue()
            ->and($display->fault()->getMessage())->toBe('bus gone')
            ->and($panel->calls)->toBe([])
            ->and($mail)->toHaveCount(1)
            ->and($mail[0])->toBeInstanceOf(DisplayFaulted::class)
            ->and($mail[0]->display)->toBe('panel')
            ->and($mail[0]->error)->toBe('bus gone');
    });
});

describe('close()', function () {
    it('switches the panel off once, tells its manager, and refuses everything after', function () {
        $closed = [];
        $panel = new FakeWindowPanel(16, 16, panelRgb565());
        $display = panelDisplay($panel, on_close: function (string $name) use (&$closed): void {
            $closed[] = $name;
        });
        $display->framebuffer();

        $display->close();
        $display->close();

        expect($display->isOpen())->toBeFalse()
            ->and($display->isVisible())->toBeFalse()
            ->and($panel->verbs())->toBe(['display'])
            ->and($closed)->toBe(['panel']);

        $display->present();
    })->throws(EmbeddedDisplayException::class, "Embedded display 'panel' is closed.");
});
