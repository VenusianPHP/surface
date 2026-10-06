<?php

namespace Surface\EmbeddedDisplays;

use Closure;
use GeneralPurposeIO\Contracts\IntegratedCircuits\DisplayPanel;
use GeneralPurposeIO\Contracts\IntegratedCircuits\PipeablePanel;
use GeneralPurposeIO\Contracts\SPI\WritesFromMemory;
use Surface\Contracts\EmbeddedDisplays\DirectEDisplay as DirectEDisplayContract;
use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplayException;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\GLFramebuffer;
use Surface\Contracts\Framebuffers\PagedFramebuffer;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Framebuffers\ScanDirection;
use Surface\Framebuffers\FramebufferManager;
use Surface\Framebuffers\Layout;

/**
 * An EmbeddedDisplay that pipes: it sends the same regions, each as
 * openWindow() then one writeFrom() over the framebuffer's memory — one span
 * for a full-width region, whose rows are contiguous, one a row otherwise.
 *
 * A GPU engine's framebuffer has no memory of its own to pipe from: bound
 * here, it is given a staging copy on ext-fb in the panel's format, each
 * region is brought up to date before it is sent, and the staging copy's
 * memory is piped.
 */
class DirectEDisplay extends EmbeddedDisplay implements DirectEDisplayContract
{
    protected readonly WritesFromMemory $bus;

    public function __construct(
        string $name,
        DisplayPanel $panel,
        FramebufferManager $framebuffers,
        array $kinds,
        ?Closure $on_close = null,
        ?Closure $post = null,
    ) {
        if (! $panel instanceof PipeablePanel) {
            throw EmbeddedDisplayException::notPipeable($name, get_debug_type($panel));
        }
        $this->bus = $panel->pixelBus() ?? throw EmbeddedDisplayException::noMemoryBus($name);

        parent::__construct($name, $panel, $framebuffers, $kinds, $on_close, $post);
    }

    /** As EmbeddedDisplay's, minted on the extended driver: the pipe reads ext-fb memory. */
    public function framebuffer(?string $kind = null, ?int $page_rows = null, int $frames = 2, ?string $driver = null): Framebuffer
    {
        if (! is_null($driver) && $driver !== 'extended') {
            throw EmbeddedDisplayException::cannotPipe($this->name, "the '{$driver}' driver keeps its pixels in PHP; a direct display mints on 'extended'");
        }
        if ($kind === 'paged') {
            throw EmbeddedDisplayException::cannotPipe($this->name, 'a paged framebuffer holds one page, not the frame');
        }

        return parent::framebuffer($kind, $page_rows, $frames, 'extended');
    }

    public function bind(Framebuffer $framebuffer): static
    {
        if ($framebuffer instanceof GLFramebuffer) {
            $why = static::formatRefusal($this->wireFormat());
            if (! is_null($why)) {
                throw EmbeddedDisplayException::cannotPipe($this->name, $why);
            }
            $staging = $this->stagingCopy();
            parent::bind($framebuffer);
            $framebuffer->stageIn($staging);

            return $this;
        }

        $why = $this->pipeRefusal($framebuffer);
        if (! is_null($why)) {
            throw EmbeddedDisplayException::cannotPipe($this->name, $why);
        }

        return parent::bind($framebuffer);
    }

    public function canPipe(Framebuffer $framebuffer): bool
    {
        return $framebuffer instanceof GLFramebuffer
            ? is_null(static::formatRefusal($this->wireFormat()))
            : is_null($this->pipeRefusal($framebuffer));
    }

    /** A framebuffer in the panel's format on ext-fb, the size of the panel: where a GPU framebuffer is staged. */
    protected function stagingCopy(): Framebuffer
    {
        return $this->framebuffers->driver('extended')->full($this->wireFormat(), $this->panel->width(), $this->panel->height());
    }

    /** Open the region as the panel's window and send its rows straight out of memory: the framebuffer's, or a GPU framebuffer's staging copy brought up to date first. */
    protected function send(Framebuffer $framebuffer, Region $region, bool $whole): void
    {
        $source = $framebuffer instanceof GLFramebuffer ? $framebuffer->stage($region) : $framebuffer;
        if (! $source->hostFormat()->equals($this->wireFormat())) {
            throw EmbeddedDisplayException::formatChanged($this->name);
        }
        $spans = $this->spans($source, $region);
        $expected = array_sum(array_column($spans, 1));
        /** @var PipeablePanel $panel */
        $panel = $this->panel;

        $this->guarded(function () use ($panel, $region, $spans, $expected): void {
            $panel->openWindow($region->x, $region->y, $region->width, $region->height);
            $written = $this->bus->writeFrom($spans);
            if ($written !== $expected) {
                throw EmbeddedDisplayException::pipeShort($this->name, $expected, $written);
            }
            $this->sent = true;
        });
    }

    /**
     * [address, length] for each run of the region in the framebuffer's memory.
     * ext-fb packs rows tightly: a row is the width times the bytes a pixel.
     *
     * @return list<array{int, int}>
     */
    protected function spans(Framebuffer $framebuffer, Region $region): array
    {
        $bytes = self::bytesPerPixel($framebuffer->hostFormat());
        $stride = $framebuffer->viewportWidth() * $bytes;
        $start = $framebuffer->pointer() + $region->y * $stride + $region->x * $bytes;
        if ($region->width === $framebuffer->viewportWidth()) {
            return [[$start, $region->height * $stride]];
        }

        $spans = [];
        for ($row = 0; $row < $region->height; $row++) {
            $spans[] = [$start + $row * $stride, $region->width * $bytes];
        }

        return $spans;
    }

    /** Why $framebuffer cannot be piped to this panel; null when it can. */
    protected function pipeRefusal(Framebuffer $framebuffer): ?string
    {
        $format = $this->wireFormat();

        return match (true) {
            $framebuffer->pointer() === 0 => 'its pixels are not in C memory (the native driver)',
            $framebuffer instanceof PagedFramebuffer => 'a paged framebuffer holds one page, not the frame',
            ! $framebuffer->hostFormat()->equals($format) => "its format is not the panel's",
            default => static::formatRefusal($format),
        };
    }

    /** Why no framebuffer in $format can be piped; null when one can. */
    protected static function formatRefusal(FormatSpec $format): ?string
    {
        return match (true) {
            $format->scan_direction !== ScanDirection::TOP_TO_BOTTOM => 'its rows run bottom-up',
            is_null(self::bytesPerPixel($format)) => 'the panel packs pixels into part of a byte, or into planes',
            default => null,
        };
    }

    /** Bytes a pixel in the layouts that give every pixel whole bytes of its own (ext-fb's fb_pixel_bytes); null for the rest. */
    protected static function bytesPerPixel(FormatSpec $format): ?int
    {
        return match (Layout::of($format)) {
            Layout::INDEX8 => 1,
            Layout::RGB565 => 2,
            Layout::RGB666, Layout::RGB888 => 3,
            Layout::RGBA8888 => 4,
            default => null,
        };
    }
}
