<?php

use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Drawing\Rasterizer;
use Surface\Framebuffers\Php\FullFramebuffer;
use Surface\Framebuffers\PixelMapper;
use Venusian\Surface\Tests\Support\Fakes\RecordingFramebuffer;

/** @return array{Rasterizer, Framebuffer} */
function raster(int $w = 8, int $h = 8, bool $record = false): array
{
    $spec = new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1);
    $fb = new FullFramebuffer($spec, $w, $h);
    $target = $record ? new RecordingFramebuffer($fb) : $fb;

    return [new Rasterizer($target, PixelMapper::for($spec)), $target];
}

/** @return list<string> */
function rows(Framebuffer $fb): array
{
    $out = [];
    for ($y = 0; $y < $fb->viewportHeight(); $y++) {
        $row = '';
        for ($x = 0; $x < $fb->viewportWidth(); $x++) {
            $row .= $fb->getPixel($x, $y) ? '#' : '.';
        }
        $out[] = $row;
    }

    return $out;
}

$white = Color::hex('#fff');

it('fillRect rounds edges to pixel boundaries', function () use ($white) {
    [$g, $fb] = raster();
    $g->fillRect(3.0, 3.0, 1.0, 1.0, $white)->fillRect(0.4, 0.0, 2.0, 1.0, $white);

    expect(rows($fb))->toBe(['##......', '........', '........', '...#....', '........', '........', '........', '........']);
});

it('fillRect under translate is spans; under rotate it is a polygon', function () use ($white) {
    [$g, $fb] = raster(8, 8, true);
    $g->push()->translate(1.0, 1.0)->fillRect(0.0, 0.0, 2.0, 2.0, $white)->pop();
    $g->push()->translate(6.0, 2.0)->rotate(M_PI / 2)->fillRect(0.0, 0.0, 2.0, 4.0, $white)->pop();

    // rect one: (1,1) 2x2. rect two: +90° about its origin then translated to (6, 2) → x 2..5, y 2..3. Row 2 holds both.
    expect(rows($fb->inner))->toBe(['........', '.##.....', '.#####..', '..####..', '........', '........', '........', '........'])
        ->and($fb->calls[0])->toBe('setSegment:1,1,2,2')            // the translated rect is one call
        ->and($fb->calls[1])->toBe('setSegment:2,2,4,1')            // the rotated one is a span per row
        ->and($fb->calls[2])->toBe('setSegment:2,3,4,1');
});

it('a one-pixel line is Bresenham in one setPixels call', function () use ($white) {
    [$g, $fb] = raster(8, 4, true);
    $g->line(0.0, 0.0, 7.0, 3.0, $white);

    expect(rows($fb->inner))->toBe(['##......', '..##....', '....##..', '......##'])
        ->and($fb->calls)->toBe(['setPixels:8']);
});

it('a wide line is a filled quad', function () use ($white) {
    [$g, $fb] = raster(8, 4);
    $g->line(0.0, 2.0, 8.0, 2.0, $white, 2.0);

    expect(rows($fb))->toBe(['........', '########', '########', '........']);
});

it('strokeRect is a one-pixel frame', function () use ($white) {
    [$g, $fb] = raster(6, 4);
    $g->strokeRect(0.0, 0.0, 6.0, 4.0, $white);

    expect(rows($fb))->toBe(['######', '#....#', '#....#', '######']);
});

it('fillTriangle and fillPolygon scanline-fill', function () use ($white) {
    [$g, $fb] = raster(8, 4);
    $g->fillTriangle(0.0, 0.0, 8.0, 0.0, 0.0, 4.0, $white);

    expect(rows($fb))->toBe(['#######.', '#####...', '###.....', '#.......']);
});

it('fillCircle is symmetric and stays inside its radius', function () use ($white) {
    [$g, $fb] = raster(8, 8);
    $g->fillCircle(4.0, 4.0, 3.0, $white);
    $r = rows($fb);

    // pixel centres sampled: row 1 has half-width 1.66 about x 4 → centres 2.5..5.5
    expect($r)->toBe(['........', '..####..', '.######.', '.######.', '.######.', '.######.', '..####..', '........'])
        ->and($r)->toBe(array_reverse($r));
});

it('strokeCircle with a hairline is a midpoint ring', function () use ($white) {
    [$g, $fb] = raster(8, 8);
    $g->strokeCircle(4.0, 4.0, 3.0, $white);

    expect(rows($fb))->toBe(['........', '...###..', '..#...#.', '.#.....#', '.#.....#', '.#.....#', '..#...#.', '...###..']);
});

it('clip narrows every primitive; unclip restores', function () use ($white) {
    [$g, $fb] = raster(8, 2);
    $g->clip(2.0, 0.0, 4.0, 2.0)->fillRect(0.0, 0.0, 8.0, 1.0, $white)->unclip()->fillRect(0.0, 1.0, 8.0, 1.0, $white);

    expect(rows($fb))->toBe(['..####..', '########']);
});

it('clear fills the whole target with the mapped colour', function () use ($white) {
    [$g, $fb] = raster(4, 2, true);
    $g->clear($white);

    expect(rows($fb->inner))->toBe(['####', '####'])->and($fb->calls)->toBe(['fill']);
});

it('image blits with nearest scaling and skips texels under half alpha', function () use ($white) {
    [$g, $fb] = raster(8, 4, true);
    $tex = $g->texture("\xff\xff\xff\xff"."\xff\xff\xff\x00"."\xff\xff\xff\x00"."\xff\xff\xff\xff", 2, 2);   // opaque, clear / clear, opaque
    $g->image($tex, 0.0, 0.0, 4.0, 4.0);

    expect(rows($fb->inner))->toBe(['##......', '##......', '..##....', '..##....'])
        ->and($fb->calls)->toBe(['setPixels:8']);
});

it('image under rotation inverse-maps its bounding box', function () use ($white) {
    [$g, $fb] = raster(6, 6);
    $tex = $g->texture(str_repeat("\xff\xff\xff\xff", 4), 2, 2);
    $g->push()->translate(4.0, 1.0)->rotate(M_PI / 2)->image($tex, 0.0, 0.0, 2.0, 2.0)->pop();

    expect(rows($fb))->toBe(['......', '..##..', '..##..', '......', '......', '......']);
});

it('the source rect picks texels', function () use ($white) {
    [$g, $fb] = raster(4, 2);
    $tex = $g->texture("\xff\xff\xff\xff"."\x00\x00\x00\xff"."\x00\x00\x00\xff"."\xff\xff\xff\xff", 2, 2);
    $g->image($tex, 0.0, 0.0, 2.0, 2.0, [1.0, 0.0, 1.0, 2.0]);

    expect(rows($fb))->toBe(['....', '##..']);
});

it('pop with nothing pushed throws; begin resets stack and clip', function () use ($white) {
    [$g, $fb] = raster(4, 1);
    expect(fn () => $g->pop())->toThrow(DrawingException::class);
    $g->translate(2.0, 0.0)->clip(0.0, 0.0, 1.0, 1.0);
    $g->begin();
    $g->fillRect(0.0, 0.0, 4.0, 1.0, $white);

    expect(rows($fb))->toBe(['####']);
});

it('a page clip from begin() outlives unclip()', function () use ($white) {
    [$g, $fb] = raster(4, 4);
    $g->begin(new Region(0, 2, 4, 2));
    $g->unclip()->fillRect(0.0, 0.0, 4.0, 4.0, $white);

    expect(rows($fb))->toBe(['....', '....', '####', '####']);
});

it('size answers the target', function () {
    [$g] = raster(5, 3);

    expect($g->size())->toBe([5, 3]);
});

it('releaseTexture forgets the handle, and drawing it afterwards throws', function () {
    [$g] = raster();
    $tex = $g->texture(str_repeat("\xff", 16), 2, 2);
    $g->releaseTexture($tex);

    expect(fn () => $g->image($tex, 0.0, 0.0, 2.0, 2.0))->toThrow(DrawingException::class);
});
