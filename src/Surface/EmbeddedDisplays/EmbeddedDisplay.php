<?php

namespace Surface\EmbeddedDisplays;

use Closure;
use GeneralPurposeIO\Contracts\IntegratedCircuits\DisplayPanel;
use GeneralPurposeIO\Contracts\IntegratedCircuits\RefreshesOnCommand;
use GeneralPurposeIO\Contracts\IntegratedCircuits\RefreshMode;
use GeneralPurposeIO\Contracts\IntegratedCircuits\Switchable;
use GeneralPurposeIO\Contracts\IntegratedCircuits\WindowAddressable;
use Surface\Contracts\Drawing\Color;
use Surface\Contracts\Drawing\CPUDrawTarget;
use Surface\Contracts\Drawing\CPUEngine;
use Surface\Contracts\Drawing\Drawing2D;
use Surface\Contracts\Drawing\PagedDrawTarget;
use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplay as EmbeddedDisplayContract;
use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplayException;
use Surface\Contracts\EmbeddedDisplays\Events\DisplayFaulted;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Region;
use Voyager\Contracts\IOPools\PoolPump;

/**
 * A CPU canvas presented on a display panel. The canvas owns the hook, the
 * clock and the pixels; this class owns one decision — what to send. A panel
 * that addresses a window gets damage regions, any other panel the whole
 * frame. The first present and the one after show() are always whole, because
 * panel RAM is not the canvas until it has been told. A paged canvas streams
 * its pages to the panel from inside its own frame. A panel that refreshes on
 * command is refreshed once per frame, after the last write. A panel write
 * that throws latches a fault: nothing more is sent and one piece of mail goes
 * out. A sketch hook that throws is the sketch's problem and propagates.
 */
class EmbeddedDisplay implements EmbeddedDisplayContract
{
    protected bool $open = true;

    protected bool $visible = true;

    /** Panel RAM matches the canvas. */
    protected bool $primed = false;

    /** Something reached the panel since the last refresh. */
    protected bool $sent = false;

    protected ?\Throwable $fault = null;

    protected ?PoolPump $io_pool = null;

    protected RefreshMode $refresh_mode = RefreshMode::FULL;

    public function __construct(
        protected readonly string $name,
        protected readonly DisplayPanel $panel,
        protected CPUDrawTarget $canvas,
        protected ?Closure $on_close = null,
    ) {
        if ($canvas instanceof PagedDrawTarget) {
            if (! $panel instanceof WindowAddressable) {
                throw EmbeddedDisplayException::pagedNeedsWindow($name);
            }

            $canvas->onPage(fn (Region $page, string|array $bytes) => $this->send($page, $bytes), true);
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function panel(): DisplayPanel
    {
        return $this->panel;
    }

    public function canvas(): CPUDrawTarget
    {
        return $this->canvas;
    }

    /**
     * Draw through a different engine from here on. The panel, its format and
     * its size do not move; only what rasterises for it does, which is how a
     * Canvas offers one renderer choice over every kind of output.
     *
     * The next present is whole: the new canvas has never sent this panel
     * anything.
     */
    public function drawWith(CPUDrawTarget $canvas): static
    {
        if ($canvas->drawableSize() !== $this->canvas->drawableSize() || ! $canvas->hostFormat()->equals($this->canvas->hostFormat())) {
            throw EmbeddedDisplayException::rendererMismatch($this->name);
        }

        if ($canvas instanceof PagedDrawTarget) {
            if (! $this->panel instanceof WindowAddressable) {
                throw EmbeddedDisplayException::pagedNeedsWindow($this->name);
            }

            $canvas->onPage(fn (Region $page, string|array $bytes) => $this->send($page, $bytes), true);
        }

        $this->canvas = $canvas;
        $this->primed = false;

        return $this;
    }

    /** Which refresh a RefreshesOnCommand panel is asked for. Ignored by every other panel. */
    public function refreshMode(RefreshMode $mode): static
    {
        $this->refresh_mode = $mode;

        return $this;
    }

    public function setPool(PoolPump $pool): static
    {
        $this->io_pool = $pool;

        return $this;
    }

    /** One canvas frame, then the pixels to the panel. Skipped when closed, hidden or faulted. */
    public function renderFrame(): bool
    {
        if (! $this->open || ! $this->visible || $this->faulted()) {
            return false;
        }

        if (! $this->canvas->renderFrame()) {
            return false;
        }

        $this->present();

        return true;
    }

    public function present(): void
    {
        if (! $this->open || $this->faulted()) {
            return;
        }

        if (! $this->canvas instanceof PagedDrawTarget) {
            $this->sendPixels();
        }

        $this->primed = true;
        $this->refresh();
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
        $this->canvas->redraw();

        if (! $this->canvas instanceof PagedDrawTarget) {
            $this->present();
        }

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

    // ---- the canvas, forwarded

    public function engine(): CPUEngine
    {
        return $this->canvas->engine();
    }

    public function hostFormat(): FormatSpec
    {
        return $this->canvas->hostFormat();
    }

    public function drawing(): Drawing2D
    {
        return $this->canvas->drawing();
    }

    public function drawableSize(): array
    {
        return $this->canvas->drawableSize();
    }

    public function onDraw(callable $hook): static
    {
        $this->canvas->onDraw($hook);

        return $this;
    }

    public function setClearColor(Color $color): static
    {
        $this->canvas->setClearColor($color);

        return $this;
    }

    public function setContinuous(bool $continuous): static
    {
        $this->canvas->setContinuous($continuous);

        return $this;
    }

    public function redraw(): static
    {
        $this->canvas->redraw();

        return $this;
    }

    public function flush(?FormatSpec $spec = null, bool $as_array = false): string|array
    {
        return $this->canvas->flush($spec, $as_array);
    }

    public function flushRegion(Region $region, ?FormatSpec $spec = null, bool $as_array = false): string|array
    {
        return $this->canvas->flushRegion($region, $spec, $as_array);
    }

    public function damage(): array
    {
        return $this->canvas->damage();
    }

    public function rgba8(): string
    {
        return $this->canvas->rgba8();
    }

    // ---- what to send

    protected function sendPixels(): void
    {
        $damage = $this->canvas->damage();
        $addressable = $this->panel instanceof WindowAddressable;

        if (! $this->primed || (! $addressable && $damage !== [])) {
            [$width, $height] = $this->canvas->drawableSize();
            $this->send(Region::wholeSurface($width, $height), $this->canvas->flush(as_array: true));

            return;
        }

        if ($addressable) {
            foreach ($damage as $region) {
                $this->send($region, $this->canvas->flushRegion($region, as_array: true));
            }
        }
    }

    /** @param string|list<int> $bytes */
    protected function send(Region $region, string|array $bytes): void
    {
        $this->guarded(function () use ($region, $bytes): void {
            $this->panel->transmit(
                $region->x,
                $region->y,
                is_array($bytes) ? $bytes : array_values(unpack('C*', $bytes)),
                $region->width,
                $region->height,
            );
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

    /** Run one panel call. A throw latches the fault and mails it once; nothing is sent after. */
    protected function guarded(Closure $panel_call): void
    {
        if ($this->faulted()) {
            return;
        }

        try {
            $panel_call();
        } catch (\Throwable $e) {
            $this->fault = $e;
            $this->io_pool?->push(new DisplayFaulted($this->name, $e->getMessage()));
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
