<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\RasterizeDriver;
use Surface\Drawing\RenderingEngine as BaseEngine;
use Surface\Drawing\Velvet\VelvetGE;
use Surface\Framebuffers\Native\NativeFullFramebuffer;
use Surface\NutsAndBolts\Color;
use Surface\Text\FaceCache;
use Surface\Text\FontCatalog;
use Surface\Text\Shaper;
use Surface\Text\TextStyle;

/*
 * glyphs(): a shaped run drawn as one coverage() per visible glyph, at whole pixels, baseline at y.
 */

/** An engine that notes each coverage() call and draws nothing. */
final class CoverageSpyEngine extends BaseEngine
{
    /** @var list<array{int, int, float, float}> width, height, x, y */
    public array $calls = [];

    private Framebuffer $framebuffer;

    public function __construct()
    {
        $this->framebuffer = new NativeFullFramebuffer(FormatSpec::rgba8(), 100, 40);
    }

    public function name(): string
    {
        return 'coverage-spy';
    }

    public function framebuffer(): Framebuffer
    {
        return $this->framebuffer;
    }

    public function coverage(string $mask, int $width, int $height, float $x, float $y, Color $color): static
    {
        $this->calls[] = [$width, $height, $x, $y];

        return $this;
    }

    protected function execute(array $commands): void {}
}

beforeEach(function (): void {
    if (! extension_loaded('freetype') || ! extension_loaded('fontconfig') || ! extension_loaded('harfbuzz') || ! extension_loaded('intl')) {
        $this->markTestSkipped('ext-freetype, ext-harfbuzz, ext-fontconfig and intl are not loaded (run with php 8.4).');
    }
    $this->catalog = new FontCatalog();
    $this->faces = new FaceCache();
    $this->shaper = new Shaper($this->catalog, $this->faces);
});

afterEach(function (): void {
    isset($this->shaper) && $this->shaper->close();
    isset($this->faces) && $this->faces->close();
    isset($this->catalog) && $this->catalog->close();
});

it('draws each visible glyph once, at whole pixels', function (): void {
    $run = $this->shaper->shape('A b', new TextStyle());
    $spy = new CoverageSpyEngine();
    $spy->frame(fn ($g) => $g->glyphs($run, 10.3, 20.6, Color::rgb(255, 255, 255)));
    $first = $run->glyphs()[0]->mask();

    expect($spy->calls)->toHaveCount(2)   // the space has no ink
        ->and($spy->calls[0])->toBe([$first->width, $first->height, 10.0 + $first->left, 21.0 - $first->top])
        ->and(array_filter(array_merge(...array_map(fn ($c) => [$c[2], $c[3]], $spy->calls)), fn ($v) => $v != floor($v)))->toBe([]);
});

it('draws nothing when the clip leaves the text out', function (): void {
    $run = $this->shaper->shape('Hidden', new TextStyle());
    $spy = new CoverageSpyEngine();
    $spy->frame(fn ($g) => $g->clip(new Region(80, 0, 20, 40))->glyphs($run, 2, 20, Color::rgb(255, 255, 255)));

    expect($spy->calls)->toBe([]);
});

it('inks above and below the baseline for Hg in Velvet', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $buffer = $framebuffers->full(FormatSpec::rgba8(), 40, 30);
    $run = $this->shaper->shape('Hg', new TextStyle());
    (new VelvetGE($buffer, $raster))->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0))->glyphs($run, 4, 18, Color::rgb(255, 255, 255)));
    $inked = function (int $from, int $to) use ($buffer): bool {
        for ($y = $from; $y < $to; $y++) {
            for ($x = 0; $x < 40; $x++) {
                if (($buffer->getPixel($x, $y) >> 24 & 0xFF) > 0) {
                    return true;
                }
            }
        }

        return false;
    };

    expect($inked(0, 18))->toBeTrue()->and($inked(18, 30))->toBeTrue();
})->with('engine pairings');
