<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\Filter;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\FillRule;
use Surface\Contracts\Rasterize\RasterizeDriver;
use Surface\Drawing\Velvet\VelvetGE;
use Surface\Framebuffers\PixelMapper;
use Surface\NutsAndBolts\Affine;
use Surface\NutsAndBolts\Color;
use Venusian\Surface\Tests\Support\Framebuffers\Formats;
use Venusian\Surface\Tests\Support\Framebuffers\Rgba8Source;

/*
 * VelvetGE is the software engine: it carries out a frame with Rasterize and
 * the framebuffer it was given. Its frames are held to what those two give
 * when called directly, in every pairing of their drivers.
 */

function rgbaOf(Color $color): int
{
    return ((int) round($color->red * 255) << 24) | ((int) round($color->green * 255) << 16) | ((int) round($color->blue * 255) << 8) | (int) round($color->alpha * 255);
}

/** A scene that touches every shape verb. */
function scene(RenderingEngine $g): void
{
    $g->clear(Color::hex('#101820'));
    $g->fillRect(2.5, 3, 10, 6, Color::hex('#ff6600'));
    $g->fillEllipse(20, 12, 7, 5, Color::rgba(40, 200, 255, 0.5));
    $g->line(1, 22, 30, 2, Color::rgb(255, 255, 255));
    $g->polyline([[3, 3], [28, 5], [16, 20]], Color::rgb(0, 200, 0), 2.5, true);
    $g->strokeEllipse(16, 12, 12, 9, Color::rgb(60, 60, 255), 2);
    $g->fillTriangle(1, 1, 9, 2, 3, 9, Color::rgba(255, 0, 255, 0.7));
    $g->fillPolygon([[20, 14], [30, 22], [30, 14], [20, 22]], Color::rgb(255, 255, 0), FillRule::EVEN_ODD);
}

/** The same scene with Rasterize and the framebuffer called directly. */
function sceneDirectly(RasterizeDriver $raster, Framebuffer $buffer, Edges $edges, ?Region $clip = null): void
{
    $r = $raster->rasterizer($clip ?? Region::wholeSurface($buffer->viewportWidth(), $buffer->viewportHeight()), $edges);
    $buffer->fill(PixelMapper::for($buffer->hostFormat())->map(Color::hex('#101820')));
    $buffer->paintSpans($r->fillRect(2.5, 3, 10, 6), rgbaOf(Color::hex('#ff6600')));
    $buffer->paintSpans($r->fillEllipse(20, 12, 7, 5), rgbaOf(Color::rgba(40, 200, 255, 0.5)));
    $buffer->paintSpans($r->line(1, 22, 30, 2), 0xFFFFFFFF);
    $buffer->paintSpans($r->polyline([[3, 3], [28, 5], [16, 20]], 2.5, true), rgbaOf(Color::rgb(0, 200, 0)));
    $buffer->paintSpans($r->strokeEllipse(16, 12, 12, 9, 2), rgbaOf(Color::rgb(60, 60, 255)));
    $buffer->paintSpans($r->fillTriangle(1, 1, 9, 2, 3, 9), rgbaOf(Color::rgba(255, 0, 255, 0.7)));
    $buffer->paintSpans($r->fillPolygon([[20, 14], [30, 22], [30, 14], [20, 22]], FillRule::EVEN_ODD), rgbaOf(Color::rgb(255, 255, 0)));
}

it('says what it is and where it draws', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $buffer = $framebuffers->full(FormatSpec::rgba8(), 32, 24);
    $velvet = new VelvetGE($buffer, $raster);

    expect($velvet->name())->toBe('velvet')
        ->and($velvet->framebuffer())->toBe($buffer)
        ->and([$velvet->width(), $velvet->height()])->toBe([32, 24])
        ->and($velvet)->toBeInstanceOf(RenderingEngine::class);
})->with('engine pairings');

it('draws a frame to the bytes Rasterize and the framebuffer give directly', function (RasterizeDriver $raster, FramebufferDriver $framebuffers, FormatSpec $spec, Edges $edges): void {
    $drawn = $framebuffers->full($spec, 32, 24);
    $direct = $framebuffers->full($spec, 32, 24);

    (new VelvetGE($drawn, $raster, $edges))->frame(scene(...));
    sceneDirectly($raster, $direct, $edges);

    expect(bin2hex($drawn->dump()))->toBe(bin2hex($direct->dump()))
        ->and(strlen(count_chars($drawn->dump(), 3)))->toBeGreaterThan(1);   // and it drew something
})->with('engine pairings')->with([
    'rgba8, anti-aliased' => [FormatSpec::rgba8(), Edges::ANTIALIASED],
    'rgba8, hard' => [FormatSpec::rgba8(), Edges::HARD],
    'rgb565, anti-aliased' => [Formats::rgb565(), Edges::ANTIALIASED],
    'mono, hard' => [Formats::mono(), Edges::HARD],
]);

it('picks its edges from the format: anti-aliased where it can blend, hard where it cannot', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $edges = fn (FormatSpec $spec): Edges => (new VelvetGE($framebuffers->full($spec, 8, 8), $raster))->edges();

    expect($edges(FormatSpec::rgba8()))->toBe(Edges::ANTIALIASED)
        ->and($edges(Formats::rgb565()))->toBe(Edges::ANTIALIASED)
        ->and($edges(new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B4)))->toBe(Edges::ANTIALIASED)   // greys blend
        ->and($edges(Formats::mono()))->toBe(Edges::HARD)
        ->and($edges(Formats::planarBwr()))->toBe(Edges::HARD)
        ->and($edges(Formats::spectra6()))->toBe(Edges::HARD)
        ->and((new VelvetGE($framebuffers->full(FormatSpec::rgba8(), 8, 8), $raster, Edges::HARD))->edges())->toBe(Edges::HARD);

    $soft = $framebuffers->full(FormatSpec::rgba8(), 4, 1);
    (new VelvetGE($soft, $raster))->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0))->fillRect(0.5, 0, 2, 1, Color::rgb(255, 255, 255)));
    $hard = $framebuffers->full(Formats::mono(), 8, 1);
    (new VelvetGE($hard, $raster))->frame(fn (RenderingEngine $g) => $g->fillRect(0.5, 0, 2, 1, Color::rgb(255, 255, 255)));

    expect(bin2hex($soft->dump()))->toBe('808080ff'.'ffffffff'.'808080ff'.'000000ff')
        ->and(bin2hex($hard->dump()))->toBe('c0');
})->with('engine pairings');

it('draws through the transform', function (RasterizeDriver $raster, FramebufferDriver $framebuffers, Edges $edges): void {
    $drawn = $framebuffers->full(FormatSpec::rgba8(), 32, 24);
    $direct = $framebuffers->full(FormatSpec::rgba8(), 32, 24);
    $orange = Color::hex('#ff6600');

    (new VelvetGE($drawn, $raster, $edges))->frame(function (RenderingEngine $g) use ($orange): void {
        $g->push()->transform(new Affine(0.0, 1.0, -1.0, 0.0, 20.0, 2.0))->fillRect(0, 0, 10, 4, $orange)->pop();   // a quarter turn
        $g->translate(3, 14)->scale(2, 0.5)->fillEllipse(3, 6, 2, 4, $orange)->strokeRect(8, 2, 3, 12, $orange, 1.5);
    });
    $r = $raster->rasterizer(Region::wholeSurface(32, 24), $edges);
    $direct->paintSpans($r->fillRect(16, 2, 4, 10), rgbaOf($orange));
    $direct->paintSpans($r->fillEllipse(9, 17, 4, 2), rgbaOf($orange));
    $direct->paintSpans($r->polyline([[19, 15], [25, 15], [25, 21], [19, 21]], 1.5, true), rgbaOf($orange));

    expect(bin2hex($drawn->dump()))->toBe(bin2hex($direct->dump()));
})->with('engine pairings')->with([Edges::HARD, Edges::ANTIALIASED]);

it('keeps a clipped frame inside its clip', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $drawn = $framebuffers->full(FormatSpec::rgba8(), 32, 24);
    $direct = $framebuffers->full(FormatSpec::rgba8(), 32, 24);
    $clip = new Region(6, 5, 15, 11);
    $background = PixelMapper::for(FormatSpec::rgba8())->map(Color::hex('#101820'));

    (new VelvetGE($drawn, $raster, Edges::ANTIALIASED))->frame(function (RenderingEngine $g) use ($clip): void {
        $g->clip($clip);
        scene($g);
    });
    sceneDirectly($raster, $direct, Edges::ANTIALIASED, $clip);

    expect(bin2hex($drawn->dump()))->toBe(bin2hex($direct->dump()))
        ->and($drawn->getPixel(0, 0))->toBe($background);   // clear() covers the surface, clip or not
})->with('engine pairings');

it('draws images: placed, scaled, turned, faded, clipped', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $source = new Rgba8Source(hex2bin('ff0000ff'.'00ff00ff'.'0000ffff'.'ffffff80'), 2, 2);
    $drawn = $framebuffers->full(FormatSpec::rgba8(), 32, 24);
    $direct = $framebuffers->full(FormatSpec::rgba8(), 32, 24);

    (new VelvetGE($drawn, $raster))->frame(function (RenderingEngine $g) use ($source): void {
        $g->image($source, 1, 1);
        $g->image($source, 4, 4, 8, 6, 0.5, Filter::LINEAR);
        $g->push()->translate(24, 12)->rotate(0.4)->clip(new Region(16, 6, 12, 12))->image($source, -4, -4, 8, 8)->pop();
    });
    $direct->paintImage($source, Affine::translation(1.0, 1.0));
    $direct->paintImage($source, new Affine(4.0, 0.0, 0.0, 3.0, 4.0, 4.0), 128, Filter::LINEAR);
    $direct->paintImage($source, Affine::translation(24.0, 12.0)->multiply(Affine::rotation(0.4))->multiply(Affine::translation(-4.0, -4.0))->multiply(Affine::scaling(4.0, 4.0)), clip: new Region(16, 6, 12, 12));

    expect(bin2hex($drawn->dump()))->toBe(bin2hex($direct->dump()))
        ->and($drawn->getPixel(1, 1))->toBe(0xFF0000FF);
})->with('engine pairings');

it('reads an image when the frame is drawn, so a frame can draw its own framebuffer', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $buffer = $framebuffers->full(FormatSpec::rgba8(), 8, 4);
    $velvet = new VelvetGE($buffer, $raster, Edges::HARD);
    $velvet->frame(fn (RenderingEngine $g) => $g->fillRect(0, 0, 4, 4, Color::rgb(255, 0, 0)));
    $velvet->frame(fn (RenderingEngine $g) => $g->image($buffer, 4, 0, 4, 2));   // the whole frame, half size, beside itself

    expect($buffer->getPixel(4, 0))->toBe(0xFF0000FF)
        ->and($buffer->getPixel(5, 1))->toBe(0xFF0000FF)
        ->and($buffer->getPixel(6, 0))->toBe(0)
        ->and($buffer->getPixel(4, 2))->toBe(0);
})->with('engine pairings');

it('runs a ring as a swap chain: repair, draw, present', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $ring = $framebuffers->ring(FormatSpec::rgba8(), 16, 12, 3);
    $both = $framebuffers->full(FormatSpec::rgba8(), 16, 12);
    $velvet = new VelvetGE($ring, $raster, Edges::HARD);
    $red = Color::rgb(255, 0, 0);
    $green = Color::rgb(0, 255, 0);

    $velvet->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0))->fillRect(1, 1, 4, 4, $red));
    expect($ring->serial())->toBe(1)
        ->and($ring->getPixel(2, 2))->toBe(0)                    // the next back frame, not yet drawn
        ->and(bin2hex(substr($ring->toRgba8(), (2 * 16 + 2) * 4, 4)))->toBe('ff0000ff');

    $velvet->frame(fn (RenderingEngine $g) => $g->fillRect(8, 6, 3, 3, $green));   // no clear: the repair carried frame 1 over
    (new VelvetGE($both, $raster, Edges::HARD))->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0))->fillRect(1, 1, 4, 4, $red)->fillRect(8, 6, 3, 3, $green));

    expect($ring->serial())->toBe(2)
        ->and(bin2hex($ring->dump()))->toBe(bin2hex($both->dump()))
        ->and(array_map(fn (Region $r): array => [$r->x, $r->y, $r->width, $r->height], $ring->damage(1)))->toBe([[8, 6, 3, 3]]);

    $velvet->replay();
    expect($ring->serial())->toBe(3)
        ->and(bin2hex($ring->dump()))->toBe(bin2hex($both->dump()));
})->with('engine pairings');

it('leaves a dirty framebuffer\'s epoch to whoever drains it', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $dirty = $framebuffers->dirty(FormatSpec::rgba8(), 16, 12);
    $velvet = new VelvetGE($dirty, $raster, Edges::HARD);
    $rects = fn (): array => array_map(fn (Region $r): array => [$r->x, $r->y, $r->width, $r->height], $dirty->damage());

    $dirty->beginEpoch();
    $velvet->frame(fn (RenderingEngine $g) => $g->fillRect(2, 2, 3, 3, Color::rgb(255, 0, 0)));
    expect($rects())->toBe([[2, 2, 3, 3]]);

    $velvet->frame(fn (RenderingEngine $g) => $g->fillRect(10, 8, 2, 2, Color::rgb(255, 0, 0)));
    expect($rects())->toBe([[2, 2, 3, 3], [10, 8, 2, 2]]);
})->with('engine pairings');

it('draws a paged framebuffer a page at a time: frame() into the first page, replay() into the rest', function (RasterizeDriver $raster, FramebufferDriver $framebuffers, FormatSpec $spec, Edges $edges): void {
    $source = new Rgba8Source(hex2bin('ff0000ff'.'00ff00ff'.'0000ffff'.'ffffffff'), 2, 2);
    $draw = function (RenderingEngine $g) use ($source): void {
        scene($g);
        $g->image($source, 12, 9, 10, 10, 0.8, Filter::LINEAR);
    };
    $whole = $framebuffers->full($spec, 32, 24);
    (new VelvetGE($whole, $raster, $edges))->frame($draw);

    $paged = $framebuffers->paged($spec, 32, 24, 8);
    $velvet = new VelvetGE($paged, $raster, $edges);
    $pages = '';
    for ($page = 0; $page < $paged->pages(); $page++) {
        $paged->setPage($page);
        $page === 0 ? $velvet->frame($draw) : $velvet->replay();
        $pages .= $paged->dump();
    }

    expect(bin2hex($pages))->toBe(bin2hex($whole->dump()));
})->with('engine pairings')->with([
    'rgb565, anti-aliased' => [Formats::rgb565(), Edges::ANTIALIASED],
    'rgba8, hard' => [FormatSpec::rgba8(), Edges::HARD],
]);

it('draws ePaper with hard edges in the panel\'s inks', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $paper = $framebuffers->epaper(Formats::planarBwr(), 32, 24);
    $direct = $framebuffers->epaper(Formats::planarBwr(), 32, 24);
    $red = Color::rgb(255, 0, 0);
    $black = Color::rgb(0, 0, 0);

    (new VelvetGE($paper, $raster))->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(255, 255, 255))->fillRect(2, 2, 12, 8, $black)->fillEllipse(20, 14, 8, 6, $red));
    $r = $raster->rasterizer(Region::wholeSurface(32, 24), Edges::HARD);
    $direct->paintSpans($r->fillRect(2, 2, 12, 8), rgbaOf($black));
    $direct->paintSpans($r->fillEllipse(20, 14, 8, 6), rgbaOf($red));

    expect(bin2hex($paper->dump()))->toBe(bin2hex($direct->dump()))
        ->and($paper->getPixel(3, 3))->toBe(1)                   // the black ink
        ->and($paper->getPixel(20, 14))->toBe(2)                 // the red ink
        ->and($paper->getPixel(31, 0))->toBe($paper->paper());
})->with('engine pairings');
