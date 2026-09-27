<?php

namespace Surface\Canvas;

use Closure;
use Surface\Canvas\Outputs\DisplayOutput;
use Surface\Canvas\Outputs\Output;
use Surface\Canvas\Outputs\StageOutput;
use Surface\Canvas\Outputs\ViewOutput;
use Surface\Contracts\Canvas\Canvasable;
use Surface\Contracts\Canvas\CanvasException;
use Surface\Contracts\Canvas\CanvasKind;
use Surface\Contracts\Drawing\Color;
use Surface\Contracts\Drawing\CPUDrawTarget;
use Surface\Contracts\Drawing\CPUEngine;
use Surface\Contracts\Drawing\CPUEngineDriver;
use Surface\Contracts\Drawing\CPUHost;
use Surface\Contracts\Drawing\DrawsWith;
use Surface\Contracts\Drawing\DrawTarget;
use Surface\Contracts\Drawing\Drawing2D;
use Surface\Contracts\Drawing\Frame;
use Surface\Contracts\Drawing\GPUDrawTarget;
use Surface\Contracts\Drawing\GPUEngine;
use Surface\Contracts\Drawing\GPUEngineDriver;
use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplay;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\NativeWindows\Views\OSGPUView;
use Surface\Contracts\Stage\StagedWindow;
use Surface\Drawing\GPUCanvas;

/**
 * Canvas::of($output) puts one lifecycle over the things a sketch draws into:
 * a GPU view, a GPU or CPU stage, an embedded display. show, hide, close,
 * size and the frame clock read the same on all four; only the Output behind
 * it knows that a view is removed, a stage is closed and a panel is switched
 * off.
 *
 * It does not draw. draw() registers one hook, and the hook is handed the
 * output's own Drawing2D inside the frame, so a verb reaches the rasteriser
 * unchanged and damage is exactly what the sketch inked. Whether last frame's
 * pixels are still there is the output's business: a GPU target clears and
 * wants the whole scene again, a dirty or epaper canvas keeps what it has.
 * Frames are on demand until animate() says otherwise.
 *
 * engine() is the other half: where the pixels go and what makes them are two
 * choices, and the second one is free on all four. Any CPU engine or any GPU
 * engine may rasterise any output, and the hook does not change either way.
 */
class Canvas implements Canvasable
{
    protected ?Closure $hook = null;

    /** Set when the target cannot take a renderer itself and the frame is blitted in. */
    protected ?CPUDrawTarget $offscreen = null;

    /** A GPU engine this Canvas attached headless, and so must release. */
    protected ?GPUCanvas $headless = null;

    /** Who is drawing. The target's own engine until engine() says otherwise. */
    protected GPUEngine|CPUEngine $rasteriser;

    public function __construct(protected readonly DrawTarget $target, protected readonly Output $control)
    {
        // DrawTarget is engine-free on purpose; every target a Canvas takes is
        // one half or the other, and says which engine it came up with.
        $this->rasteriser = match (true) {
            $target instanceof GPUDrawTarget, $target instanceof CPUDrawTarget => $target->engine(),
            default => throw CanvasException::unsupportedOutput(get_debug_type($target)),
        };

        $target->setContinuous(false)->onDraw(fn (Drawing2D $g, Frame $frame) => $this->frame($g, $frame));
    }

    public static function of(DrawTarget $output): static
    {
        return new static($output, match (true) {
            $output instanceof EmbeddedDisplay => new DisplayOutput($output),
            $output instanceof StagedWindow => new StageOutput($output),
            $output instanceof OSGPUView => new ViewOutput($output),
            default => throw CanvasException::unsupportedOutput(get_debug_type($output)),
        });
    }

    public function output(): DrawTarget
    {
        return $this->target;
    }

    /**
     * Rasterise through this engine from here on — on any of the four.
     *
     * The point of a Canvas: where the pixels go and what makes them are two
     * choices, and this is the second one. Name any CPU engine
     * (dirty, full, epaper, paged, nframes) or any GPU engine
     * (metal, opengl, vulkan, sdl3). The output keeps its size, its format,
     * its visibility and its lifecycle; only who draws changes.
     *
     * A GPU engine needs no window to run: it attaches to nothing, makes its
     * own surface, and its frame is read back as pixels. So a panel can be
     * rasterised by Metal and a Metal window by the dirty CPU engine.
     *
     * Two mechanics, picked by what the output is. A panel or a CPU stage
     * presents pixels somebody else made, so it takes the renderer outright.
     * A GPU stage or GPU view owns the surface its own engine minted: there
     * the chosen renderer draws offscreen at size() and arrives as one texture
     * inside the target's frame — unless the engine asked for is the one the
     * target already runs, which is drawn straight through.
     */
    public function engine(GPUEngine|CPUEngine|string $engine): static
    {
        $this->guardOpen();

        $name = $engine instanceof GPUEngine || $engine instanceof CPUEngine ? $engine->value : $engine;

        if ($this->target instanceof DrawsWith && $this->target instanceof CPUDrawTarget) {
            [$width, $height] = $this->target->drawableSize();
            $this->target->drawWith($this->renderer($name, $width, $height, $this->target->hostFormat()));

            // The target's hook was the Canvas's own; the new canvas has none.
            return $this->direct();
        }

        if ($this->target instanceof GPUDrawTarget && $name === $this->target->engine()->value) {
            $this->release();
            $this->rasteriser = $this->target->engine();

            return $this->direct();
        }

        [$width, $height] = $this->control->size();
        $this->release();
        $this->offscreen = $this->renderer($name, $width, $height, static::rgba8());
        $this->offscreen->setContinuous(false)->onDraw(fn (Drawing2D $g, Frame $frame) => $this->ink($g, $frame));
        $this->target->redraw();

        return $this;
    }

    /** A CPU engine attaches to a host; a GPU engine attaches to nothing and hands its frames back. */
    protected function renderer(string $name, int $width, int $height, FormatSpec $format): CPUDrawTarget
    {
        $this->release();

        if (is_null(GPUEngine::tryFrom($name))) {
            $driver = $this->resolveCPUEngine($name);
            $this->rasteriser = $driver->engine();

            return $driver->attach(new CPUHost($width, $height, $format));
        }

        $driver = $this->resolveGPUEngine($name);
        $this->rasteriser = $driver->engine();

        return $this->headless = GPUCanvas::headless($driver, $width, $height, $format);
    }

    /** Look an engine up by name. Overridable so the flow is provable without a container. */
    protected function resolveCPUEngine(string $name): CPUEngineDriver
    {
        return app('cpu-engines')->driver($name);
    }

    /** Look an engine up by name. Overridable so the flow is provable without a container. */
    protected function resolveGPUEngine(string $name): GPUEngineDriver
    {
        return app('gpu-engines')->driver($name);
    }

    /** Back to the target's own rasteriser, with the Canvas's hook on it. */
    protected function direct(): static
    {
        $this->offscreen = null;
        $this->target->setContinuous(false)->onDraw(fn (Drawing2D $g, Frame $frame) => $this->frame($g, $frame));

        return $this;
    }

    /** What an offscreen renderer draws into when the target only wants a texture. */
    protected static function rgba8(): FormatSpec
    {
        return new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B32);
    }

    public function kind(): CanvasKind
    {
        return $this->control->kind();
    }

    /**
     * Which engine is drawing. Not the same question as output()->engine():
     * a panel rasterised by Metal still answers a CPU engine there, because
     * what it holds is finished pixels.
     */
    public function rasteriser(): GPUEngine|CPUEngine
    {
        return $this->rasteriser;
    }

    /** Whoever rasterises. Only a frame may ink with it; a texture can be minted any time. */
    public function drawing(): Drawing2D
    {
        $this->guardOpen();

        return ($this->offscreen ?? $this->target)->drawing();
    }

    public function draw(callable $hook): static
    {
        $this->guardOpen();
        $this->hook = $hook(...);

        return $this;
    }

    public function present(): static
    {
        $this->guardOpen();
        $this->target->redraw();

        return $this;
    }

    public function animate(bool $on = true): static
    {
        $this->guardOpen();
        $this->target->setContinuous($on);

        return $this;
    }

    public function background(Color $color): static
    {
        $this->guardOpen();
        $this->target->setClearColor($color);
        $this->offscreen?->setClearColor($color);

        return $this;
    }

    public function width(): int
    {
        return $this->control->size()[0];
    }

    public function height(): int
    {
        return $this->control->size()[1];
    }

    /** @return array{int, int} */
    public function size(): array
    {
        return $this->control->size();
    }

    public function show(): static
    {
        $this->guardOpen();
        $this->control->show();

        return $this;
    }

    public function hide(): static
    {
        $this->guardOpen();
        $this->control->hide();

        return $this;
    }

    public function isVisible(): bool
    {
        return $this->control->isVisible();
    }

    public function close(): void
    {
        $this->control->close();
        $this->release();
        $this->offscreen = null;
        $this->hook = null;
    }

    public function isOpen(): bool
    {
        return $this->control->isOpen();
    }

    /**
     * The output's frame. Straight to the sketch's hook, unless something else
     * is rasterising — then the frame is spent bringing its pixels in.
     */
    protected function frame(Drawing2D $g, Frame $frame): void
    {
        is_null($this->offscreen) ? $this->ink($g, $frame) : $this->blit($g);
    }

    /** The sketch's hook and nothing else. */
    protected function ink(Drawing2D $g, Frame $frame): void
    {
        if (! is_null($this->hook)) {
            ($this->hook)($g, $frame);
        }
    }

    /**
     * One offscreen frame, then its pixels over the whole target as a texture.
     * The texture is minted per frame because that is what an upload is; it is
     * released in the same frame it was drawn with.
     */
    protected function blit(Drawing2D $g): void
    {
        $this->offscreen->redraw()->renderFrame();

        [$width, $height] = $this->offscreen->drawableSize();
        $texture = $g->texture($this->offscreen->rgba8(), $width, $height);

        try {
            $g->image($texture, 0.0, 0.0, (float) $width, (float) $height);
        } finally {
            $g->releaseTexture($texture);
        }
    }

    /** Give back a GPU engine this Canvas attached headless. Whoever took it is done with it. */
    protected function release(): void
    {
        $this->headless?->release();
        $this->headless = null;
    }

    protected function guardOpen(): void
    {
        if (! $this->control->isOpen()) {
            throw CanvasException::closed();
        }
    }
}
