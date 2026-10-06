<?php

namespace Surface\EmbeddedDisplays;

use Closure;
use GeneralPurposeIO\Contracts\IntegratedCircuits\DisplayPanel;
use GeneralPurposeIO\Contracts\IntegratedCircuits\RefreshesOnCommand;
use GeneralPurposeIO\Contracts\IntegratedCircuits\RefreshMode;
use GeneralPurposeIO\Contracts\IntegratedCircuits\Switchable;
use GeneralPurposeIO\Contracts\IntegratedCircuits\WindowAddressable;
use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplay as EmbeddedDisplayContract;
use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplayException;
use Surface\Contracts\EmbeddedDisplays\Mail\DisplayFaulted;
use Surface\Contracts\Framebuffers\DamageGranularity;
use Surface\Contracts\Framebuffers\DamageTrackingFramebuffer;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\PagedFramebuffer;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Framebuffers\RingFramebuffer;
use Surface\Framebuffers\FramebufferManager;

/**
 * An IC display panel as a drawing output. It owns one decision: what to send.
 * The whole frame the first time and whenever panel RAM may differ from the
 * framebuffer; afterwards the framebuffer's damage, snapped to what the panel
 * addresses, where the panel takes region writes. A panel that refreshes on
 * command is refreshed once after the sends. A panel call that throws latches
 * a fault: nothing more is sent and one piece of mail is posted.
 */
class EmbeddedDisplay implements EmbeddedDisplayContract
{
    protected bool $open = true;

    protected bool $visible = true;

    /** Panel RAM matches the bound framebuffer. */
    protected bool $primed = false;

    /** Something reached the panel since the last refresh. */
    protected bool $sent = false;

    protected ?Framebuffer $framebuffer = null;

    /** @var array{string, int|null, int, string|null}|null What framebuffer() minted the bound one from; null for a bound one. */
    protected ?array $minted = null;

    /** The ring serial last sent. */
    protected ?int $shown = null;

    protected ?\Throwable $fault = null;

    protected RefreshMode $refresh_mode = RefreshMode::FULL;

    /**
     * @param  DisplayPanel  $panel  A chip that also answers formatSpec(): FormatSpec.
     * @param  array{refreshing: string, addressable: string, whole: string}  $kinds  The kind framebuffer() picks for each panel behaviour.
     * @param  (Closure(string): void)|null  $on_close  Told the name when the display closes.
     * @param  (Closure(object): void)|null  $post  Where the fault mail goes.
     */
    public function __construct(
        protected readonly string $name,
        protected readonly DisplayPanel $panel,
        protected readonly FramebufferManager $framebuffers,
        protected readonly array $kinds,
        protected ?Closure $on_close = null,
        protected ?Closure $post = null,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function panel(): DisplayPanel
    {
        return $this->panel;
    }

    public function framebuffer(?string $kind = null, ?int $page_rows = null, int $frames = 2, ?string $driver = null): Framebuffer
    {
        $this->guardOpen();
        $kind ??= $this->kinds[match (true) {
            $this->panel instanceof RefreshesOnCommand => 'refreshing',
            $this->panel instanceof WindowAddressable => 'addressable',
            default => 'whole',
        }];
        $format = $this->wireFormat();
        $width = $this->panel->width();
        $height = $this->panel->height();
        $wanted = [$kind, $page_rows, $frames, $driver];

        $bound = $this->framebuffer;
        if (! is_null($bound) && $this->minted === $wanted
            && $bound->viewportWidth() === $width && $bound->viewportHeight() === $height
            && $bound->hostFormat()->equals($format)) {
            return $bound;
        }

        if ($kind === 'paged' && is_null($page_rows)) {
            throw EmbeddedDisplayException::pageRowsMissing($this->name);
        }
        if (! in_array($kind, ['full', 'dirty', 'epaper', 'paged', 'ring'], true)) {
            throw EmbeddedDisplayException::unknownKind($kind);
        }
        if ($kind === 'paged') {
            $this->guardPaged($page_rows);
        }

        $from = $this->framebuffers->driver($driver);
        $framebuffer = match ($kind) {
            'full' => $from->full($format, $width, $height),
            'dirty' => $from->dirty($format, $width, $height),
            'epaper' => $from->epaper($format, $width, $height),
            'paged' => $from->paged($format, $width, $height, $page_rows),
            'ring' => $from->ring($format, $width, $height, $frames),
        };
        $this->bind($framebuffer);
        $this->minted = $wanted;

        return $framebuffer;
    }

    public function bind(Framebuffer $framebuffer): static
    {
        $this->guardOpen();
        $width = $this->panel->width();
        $height = $this->panel->height();
        if ($framebuffer->viewportWidth() !== $width || $framebuffer->viewportHeight() !== $height) {
            throw EmbeddedDisplayException::sizeMismatch($this->name, $width, $height, $framebuffer->viewportWidth(), $framebuffer->viewportHeight());
        }
        if ($framebuffer instanceof PagedFramebuffer) {
            $this->guardPaged($framebuffer->pageRows());
        }

        $this->framebuffer = $framebuffer;
        $this->minted = null;
        $this->primed = false;
        $this->shown = null;

        return $this;
    }

    public function boundFramebuffer(): ?Framebuffer
    {
        return $this->framebuffer;
    }

    public function pixelSize(): array
    {
        return [$this->panel->width(), $this->panel->height()];
    }

    public function pixelFormat(): FormatSpec
    {
        return $this->wireFormat();
    }

    /** Which refresh a panel that refreshes on command is asked for. Ignored by every other panel. */
    public function refreshMode(RefreshMode $mode): static
    {
        $this->refresh_mode = $mode;

        return $this;
    }

    public function present(): static
    {
        $this->guardOpen();
        $framebuffer = $this->framebuffer ?? throw EmbeddedDisplayException::nothingBound($this->name);
        if (! $this->visible || $this->faulted()) {
            return $this;
        }

        if ($framebuffer instanceof PagedFramebuffer) {
            $this->send($framebuffer, $framebuffer->pageRegion($framebuffer->page()), true);
            if ($framebuffer->page() === $framebuffer->pages() - 1) {
                $this->primed = true;
                $this->refresh();
            }

            return $this;
        }

        foreach ($this->changed($framebuffer) as $region) {
            $whole = $region->width === $framebuffer->viewportWidth() && $region->height === $framebuffer->viewportHeight();
            $this->send($framebuffer, $region, $whole);
        }
        $this->primed = true;
        if ($framebuffer instanceof DamageTrackingFramebuffer) {
            $framebuffer->beginEpoch();
        }
        if ($framebuffer instanceof RingFramebuffer) {
            $this->shown = $framebuffer->serial();
        }
        $this->refresh();

        return $this;
    }

    public function show(): static
    {
        $this->guardOpen();
        $panel = $this->switchablePanel();
        if ($this->visible) {
            return $this;
        }

        $this->guarded(fn () => $panel->setDisplay(true));
        $this->visible = true;
        $this->primed = false;

        return $this;
    }

    public function hide(): static
    {
        $this->guardOpen();
        $panel = $this->switchablePanel();
        if (! $this->visible) {
            return $this;
        }

        $this->guarded(fn () => $panel->setDisplay(false));
        $this->visible = false;

        return $this;
    }

    public function isVisible(): bool
    {
        return $this->open && $this->visible;
    }

    public function switchable(): bool
    {
        return $this->panel instanceof Switchable;
    }

    public function close(): void
    {
        if (! $this->open) {
            return;
        }

        $this->open = false;

        try {
            if ($this->panel instanceof Switchable && ! $this->faulted()) {
                $this->panel->setDisplay(false);
            }
        } finally {
            if (! is_null($this->on_close)) {
                ($this->on_close)($this->name);
            }
        }
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    public function faulted(): bool
    {
        return ! is_null($this->fault);
    }

    public function fault(): ?\Throwable
    {
        return $this->fault;
    }

    /**
     * The regions to send: the whole surface until the panel is primed, or for
     * any damage where the panel takes whole frames only; otherwise the
     * damage, snapped to what the panel addresses and to what the framebuffer
     * drains, overlapping regions merged.
     *
     * @return list<Region>
     */
    protected function changed(Framebuffer $framebuffer): array
    {
        $whole = Region::wholeSurface($framebuffer->viewportWidth(), $framebuffer->viewportHeight());
        if (! $this->primed) {
            return [$whole];
        }

        $damage = match (true) {
            $framebuffer instanceof RingFramebuffer => $this->shown === $framebuffer->serial() ? [] : $framebuffer->damage($this->shown),
            $framebuffer instanceof DamageTrackingFramebuffer => $framebuffer->damage(),
            default => [$whole],
        };
        if ($damage === []) {
            return [];
        }
        if (! $this->panel instanceof WindowAddressable) {
            return [$whole];
        }

        $wire = $this->wireGranularity();
        $drain = $framebuffer->damageGranularity();
        $merged = [];
        foreach ($damage as $region) {
            $region = $region->snap($wire)->snap($drain);
            do {
                $grew = false;
                foreach ($merged as $index => $kept) {
                    if (! is_null($kept->intersect($region))) {
                        $region = $region->union($kept)->snap($wire)->snap($drain);
                        unset($merged[$index]);
                        $grew = true;
                    }
                }
            } while ($grew);
            $merged[] = $region;
        }

        return array_values($merged);
    }

    /** One transmit of $region, packed in the panel's format. */
    protected function send(Framebuffer $framebuffer, Region $region, bool $whole): void
    {
        $format = $this->wireFormat();
        $bytes = $whole ? $framebuffer->flush($format, true) : $framebuffer->flushRegion($region, $format, true);

        $this->guarded(function () use ($region, $bytes): void {
            $this->panel->transmit($region->x, $region->y, $bytes, $region->width, $region->height);
            $this->sent = true;
        });
    }

    protected function refresh(): void
    {
        if ($this->sent && $this->panel instanceof RefreshesOnCommand) {
            $panel = $this->panel;
            $this->guarded(fn () => $panel->refresh($this->refresh_mode));
        }

        $this->sent = false;
    }

    /** How the panel wants its bytes. Read on every call: a chip changes it when it is rotated or its colour mode changes. */
    protected function wireFormat(): FormatSpec
    {
        return $this->panel->formatSpec();
    }

    /**
     * The smallest window the panel takes: 8-row pages across the whole width
     * for a vertical-page format, whole bytes across for a format under 8 bits
     * a pixel, one pixel otherwise.
     */
    protected function wireGranularity(): DamageGranularity
    {
        $format = $this->wireFormat();
        $width = $this->panel->width();
        $height = $this->panel->height();
        if ($format->pixel_format === PixelFormat::MONO_VERTICAL_PAGE) {
            return DamageGranularity::rows(8, $width, $height);
        }
        $bits = $format->bit_depth->value;

        return new DamageGranularity($bits < 8 ? intdiv(8, $bits) : 1, 1, $width, $height);
    }

    /** A page is a window of whole panel rows: the panel must take region writes, and a page must hold whole units. */
    protected function guardPaged(int $page_rows): void
    {
        if (! $this->panel instanceof WindowAddressable) {
            throw EmbeddedDisplayException::pagedNeedsWindow($this->name);
        }
        $unit = $this->wireGranularity()->unit_height;
        if ($page_rows % $unit !== 0) {
            throw EmbeddedDisplayException::pageRowsUnaligned($this->name, $page_rows, $unit);
        }
    }

    /** Run one panel call. A throw latches the fault and posts it once; nothing is sent after. */
    protected function guarded(Closure $panel_call): void
    {
        if ($this->faulted()) {
            return;
        }

        try {
            $panel_call();
        } catch (\Throwable $e) {
            $this->fault = $e;
            if (! is_null($this->post)) {
                ($this->post)(new DisplayFaulted($this->name, $e->getMessage()));
            }
        }
    }

    protected function guardOpen(): void
    {
        if (! $this->open) {
            throw EmbeddedDisplayException::closed($this->name);
        }
    }

    protected function switchablePanel(): Switchable
    {
        return $this->panel instanceof Switchable ? $this->panel : throw EmbeddedDisplayException::notSwitchable($this->name);
    }
}
