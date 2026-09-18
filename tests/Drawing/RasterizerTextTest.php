<?php

use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Drawing\Rasterizer;
use Surface\Fonts\ClassicFont;
use Surface\Framebuffers\Php\FullFramebuffer;
use Surface\Framebuffers\PixelMapper;
use Venusian\Surface\Tests\Support\Fakes\RecordingFramebuffer;
use Venusian\Surface\Tests\Support\Fakes\TinyAAFace;
use Venusian\Surface\Tests\Support\Fakes\TinyFace;

/** @return array{Rasterizer, Framebuffer} */
function textRaster(int $w = 8, int $h = 8, bool $record = false): array
{
    $spec = new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1);
    $fb = new FullFramebuffer($spec, $w, $h);
    $target = $record ? new RecordingFramebuffer($fb) : $fb;

    return [new Rasterizer($target, PixelMapper::for($spec)), $target];
}

/** @return list<string> */
function textRows(Framebuffer $fb): array
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

it("draws the classic 'A' as one span per run", function () use ($white) {
    [$g, $fb] = textRaster(8, 8, true);
    $g->text('A', 1.0, 0.0, $white, new ClassicFont());

    expect(textRows($fb->inner))->toBe(['...#....', '..#.#...', '.#...#..', '.#...#..', '.#####..', '.#...#..', '.#...#..', '........'])
        ->and($fb->calls)->toHaveCount(12)
        ->and($fb->calls[0])->toBe('setSegment:3,0,1,1')
        ->and($fb->calls[7])->toBe('setSegment:1,4,5,1');
});

it('a translation is the fast path; a rotation goes through the polygon fill', function () use ($white) {
    [$g, $fb] = textRaster(8, 8, true);
    $g->push()->translate(1.0, 1.0)->text('C', 0.0, 0.0, $white, new TinyFace())->pop();

    expect(textRows($fb->inner))->toBe(['........', '........', '.##.....', '.#......', '.##.....', '........', '........', '........'])
        ->and($fb->calls)->toBe(['setSegment:1,2,2,1', 'setSegment:1,3,1,1', 'setSegment:1,4,2,1']);

    [$g, $fb] = textRaster(8, 8, true);
    $g->push()->translate(4.0, 0.0)->rotate(M_PI / 2)->text('C', 0.0, -1.0, $white, new TinyFace())->pop();

    expect(textRows($fb->inner)[0])->toBe('.###....')
        ->and(textRows($fb->inner)[1])->toBe('.#.#....')
        ->and(array_unique(array_map(fn (string $c) => substr($c, -2), $fb->calls)))->toBe([',1']);
});

it('clips glyphs, thresholds 4bpp faces, and starts a new line at lineHeight', function () use ($white) {
    [$g, $fb] = textRaster();
    $g->clip(0.0, 0.0, 3.0, 8.0)->text('A', 1.0, 0.0, $white, new ClassicFont())->unclip();
    expect(textRows($fb)[4])->toBe('.##.....')->and(textRows($fb)[0])->toBe('........');

    [$g, $fb] = textRaster();
    $g->text('A', 0.0, 0.0, $white, new TinyAAFace());
    expect(textRows($fb))->toBe(['........', '........', '........', '........', '........', '........', '#.......', '#.......']);

    [$g, $fb] = textRaster(8, 16);
    $g->text("A\nA", 0.0, 0.0, $white, new TinyFace());
    expect(textRows($fb)[0])->toBe('###.....')->and(textRows($fb)[8])->toBe('###.....')->and(textRows($fb)[9])->toBe('#.#.....');
});

it('an empty face writes nothing and textBounds matches the typesetter', function () use ($white) {
    [$g, $fb] = textRaster(8, 8, true);
    $empty = new class extends \Surface\Contracts\Fonts\GFXFont {};
    $g->text('AB', 0.0, 0.0, $white, $empty);

    expect($fb->calls)->toBe([])
        ->and($g->textBounds("AB\nC", new TinyFace()))->toBe([0.0, 0.0, 7.0, 12.0]);
});
