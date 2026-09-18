<?php

use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Drawing\ExecutorCapabilities;
use Surface\Contracts\Drawing\Topology;
use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Drawing\Painter;
use Venusian\Surface\Tests\Support\Fakes\FakeExecutor;

function painter(?ExecutorCapabilities $caps = null, int $w = 200, int $h = 100, float $scale = 1.0): array
{
    $executor = new FakeExecutor($caps);
    $executor->resize((int) ($w * $scale), (int) ($h * $scale));
    $painter = new Painter($executor);
    $painter->begin($w, $h, $scale);

    return [$painter, $executor];
}

it('fillRect is two triangles in pixels with the colour on every vertex', function () {
    [$g, $x] = painter();
    $g->fillRect(10.0, 20.0, 30.0, 40.0, new Color(1.0, 0.5, 0.0, 1.0))->flush();

    expect($x->draws)->toHaveCount(1)
        ->and($x->draws[0]['topology'])->toBe(Topology::TRIANGLES)
        ->and($x->draws[0]['count'])->toBe(6)
        ->and($x->draws[0]['texture'])->toBeNull();
    $rows = $x->vertices(0);
    expect($rows[0])->toBe([10.0, 20.0, 0.0, 1.0, 0.5, 0.0, 1.0, 0.0, 0.0])
        ->and($rows[2])->toBe([40.0, 60.0, 0.0, 1.0, 0.5, 0.0, 1.0, 0.0, 0.0]);
});

it('the transform handed to the executor is the projection, identical for every draw', function () {
    [$g, $x] = painter();
    $g->fillRect(0.0, 0.0, 1.0, 1.0, Color::hex('#fff'))
      ->image($g->texture(str_repeat("\xff", 16), 2, 2), 0.0, 0.0, 4.0, 4.0)
      ->flush();

    expect($x->draws)->toHaveCount(2)
        ->and($x->draws[0]['transform']->toPacked())->toBe($x->draws[1]['transform']->toPacked())
        ->and($x->draws[0]['transform']->apply(0.0, 0.0))->toBe([-1.0, 1.0])
        ->and($x->draws[0]['transform']->apply(200.0, 100.0))->toBe([1.0, -1.0]);
});

it('points scale to pixels by the backing scale and the projection uses the drawable', function () {
    [$g, $x] = painter(null, 200, 100, 2.0);
    $g->fillRect(10.0, 10.0, 10.0, 10.0, Color::hex('#fff'))->flush();

    expect($x->vertices(0)[0][0])->toBe(20.0)
        ->and($x->draws[0]['transform']->apply(400.0, 200.0))->toBe([1.0, -1.0]);
});

it('consecutive shapes with one topology and no texture share one draw', function () {
    [$g, $x] = painter();
    $g->fillRect(0.0, 0.0, 1.0, 1.0, Color::hex('#fff'))
      ->fillTriangle(0.0, 0.0, 1.0, 0.0, 0.0, 1.0, Color::hex('#fff'))
      ->flush();

    expect($x->draws)->toHaveCount(1)
        ->and($x->draws[0]['count'])->toBe(9);
});

it('a texture change flushes the batch', function () {
    [$g, $x] = painter();
    $tex = $g->texture(str_repeat("\xff", 16), 2, 2);
    $g->fillRect(0.0, 0.0, 1.0, 1.0, Color::hex('#fff'))
      ->image($tex, 0.0, 0.0, 2.0, 2.0)
      ->fillRect(0.0, 0.0, 1.0, 1.0, Color::hex('#fff'))
      ->flush();

    expect($x->draws)->toHaveCount(3)
        ->and($x->draws[1]['texture'])->toBe($tex)
        ->and($x->draws[1]['count'])->toBe(6);
});

it('a one-pixel line is LINES; a wider line is a quad', function () {
    [$g, $x] = painter();
    $g->line(0.0, 0.0, 10.0, 0.0, Color::hex('#fff'))->flush();
    $g->line(0.0, 0.0, 10.0, 0.0, Color::hex('#fff'), 4.0)->flush();

    expect($x->draws[0]['topology'])->toBe(Topology::LINES)
        ->and($x->draws[0]['count'])->toBe(2)
        ->and($x->draws[1]['topology'])->toBe(Topology::TRIANGLES)
        ->and($x->draws[1]['count'])->toBe(6);
    $quad = $x->vertices(1);
    expect($quad[0][1])->toBe(-2.0)
        ->and($quad[1][1])->toBe(2.0);
});

it('a circle uses max(12, ceil(r/2)) segments capped at 256, as a triangle list', function () {
    [$g, $x] = painter();
    $g->fillCircle(50.0, 50.0, 10.0, Color::hex('#fff'))->flush();
    $g->fillCircle(50.0, 50.0, 100.0, Color::hex('#fff'))->flush();
    $g->fillCircle(50.0, 50.0, 10000.0, Color::hex('#fff'))->flush();

    expect($x->draws[0]['count'])->toBe(12 * 3)
        ->and($x->draws[1]['count'])->toBe(50 * 3)
        ->and($x->draws[2]['count'])->toBe(256 * 3)
        ->and($x->draws[0]['topology'])->toBe(Topology::TRIANGLES);
});

it('strokeRect is four quads and polyline is a quad per segment, closed adds one', function () {
    [$g, $x] = painter();
    $g->strokeRect(0.0, 0.0, 10.0, 10.0, Color::hex('#fff'), 2.0)->flush();
    $g->polyline([[0.0, 0.0], [10.0, 0.0], [10.0, 10.0]], Color::hex('#fff'), 2.0)->flush();
    $g->polyline([[0.0, 0.0], [10.0, 0.0], [10.0, 10.0]], Color::hex('#fff'), 2.0, true)->flush();

    expect($x->draws[0]['count'])->toBe(24)
        ->and($x->draws[1]['count'])->toBe(12)
        ->and($x->draws[2]['count'])->toBe(18);
});

it('fillPolygon fans from vertex 0 as a list', function () {
    [$g, $x] = painter();
    $g->fillPolygon([[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0], [-5.0, 5.0]], Color::hex('#fff'))->flush();

    expect($x->draws[0]['count'])->toBe(9)
        ->and($x->vertices(0)[3][0])->toBe(0.0)
        ->and($x->vertices(0)[4][0])->toBe(10.0);
});

it('image emits six textured vertices with uv from the source rect in texels', function () {
    [$g, $x] = painter();
    $tex = $g->texture(str_repeat("\xff", 4 * 4 * 4), 4, 4);
    $g->image($tex, 0.0, 0.0, 8.0, 8.0, [2.0, 0.0, 2.0, 4.0])->flush();

    $rows = $x->vertices(0);
    expect($x->draws[0]['texture'])->toBe($tex)
        ->and($rows[0][7])->toBe(0.5)->and($rows[0][8])->toBe(0.0)
        ->and($rows[2][7])->toBe(1.0)->and($rows[2][8])->toBe(1.0)
        ->and($rows[0][3])->toBe(1.0)->and($rows[0][6])->toBe(1.0);
});

it('push/translate/rotate/pop apply to vertices in order and restore', function () {
    [$g, $x] = painter();
    $g->push()->translate(100.0, 0.0)->rotate(M_PI / 2)
      ->fillRect(0.0, 0.0, 10.0, 10.0, Color::hex('#fff'))
      ->pop()
      ->fillRect(0.0, 0.0, 10.0, 10.0, Color::hex('#fff'))
      ->flush();

    $rows = $x->vertices(0);
    expect(round($rows[1][0], 6))->toBe(100.0)
        ->and(round($rows[1][1], 6))->toBe(10.0)
        ->and($rows[6])->toBe([0.0, 0.0, 0.0, 1.0, 1.0, 1.0, 1.0, 0.0, 0.0]);
});

it('pop with nothing pushed throws', function () {
    [$g] = painter();

    expect(fn () => $g->pop())->toThrow(DrawingException::class);
});

it('begin resets a leftover clip on the executor', function () {
    [$g, $x] = painter();
    $g->clip(10.0, 20.0, 30.0, 40.0);
    $g->begin(200, 100, 1.0);

    expect($x->scissors[count($x->scissors) - 1])->toBeNull();
});

it('clip flushes, scissors in pixels by the backing scale, and unclip restores', function () {
    [$g, $x] = painter(null, 200, 100, 2.0);
    $g->fillRect(0.0, 0.0, 1.0, 1.0, Color::hex('#fff'))
      ->clip(10.0, 20.0, 30.0, 40.0)
      ->fillRect(0.0, 0.0, 1.0, 1.0, Color::hex('#fff'))
      ->unclip()
      ->flush();

    expect($x->scissors)->toBe([null, [20, 40, 60, 80], null])
        ->and($x->calls)->toBe(['resize', 'unscissor', 'draw', 'scissor', 'draw', 'unscissor']);
});

it('draws opaque when the executor cannot blend', function () {
    $caps = new ExecutorCapabilities(blending: false, depth: false, instancing: true, readback: true, max_texture_size: 4096);
    [$g, $x] = painter($caps);
    $tex = $g->texture(str_repeat("\xff", 16), 2, 2);
    $g->fillRect(0.0, 0.0, 1.0, 1.0, new Color(1.0, 0.0, 0.0, 0.25))->flush();
    $g->image($tex, 0.0, 0.0, 2.0, 2.0, null, 0.1)->flush();

    expect($x->vertices(0)[0][6])->toBe(1.0)
        ->and($x->vertices(1)[0][6])->toBe(1.0);
});

it('clear is a full-target rect, mid-frame honest', function () {
    [$g, $x] = painter();
    $g->clear(Color::hex('#000'))->flush();

    $rows = $x->vertices(0);
    expect($x->draws[0]['count'])->toBe(6)
        ->and($rows[2][0])->toBe(200.0)->and($rows[2][1])->toBe(100.0);
});

it('readPixels is legal only between beginFrame and endFrame', function () {
    $x = new FakeExecutor();

    expect(fn () => $x->readPixels())->toThrow(DrawingException::class);
    $x->beginFrame(Color::hex('#000'));
    expect($x->readPixels())->toBe('');
    $x->endFrame();
    expect(fn () => $x->readPixels())->toThrow(DrawingException::class);
});

it('size answers points and reset drops a partial batch', function () {
    [$g, $x] = painter();
    $g->fillRect(0.0, 0.0, 1.0, 1.0, Color::hex('#fff'));
    $g->reset();
    $g->flush();

    expect($g->size())->toBe([200, 100])
        ->and($x->draws)->toHaveCount(0);
});

it('releaseTexture hands the handle back to the executor', function () {
    [$g, $x] = painter();
    $tex = $g->texture(str_repeat("\xff", 16), 2, 2);
    $g->releaseTexture($tex);

    expect($x->released_textures)->toBe([$tex->id])
        ->and($x->textures)->toBe([]);
});
