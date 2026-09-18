<?php

use Surface\Contracts\Drawing\ExecutorCapabilities;
use Surface\Contracts\Drawing\Topology;
use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Drawing\Painter;
use Venusian\Surface\Tests\Support\Fakes\FakeExecutor;
use Venusian\Surface\Tests\Support\Fakes\TinyFace;

/** @return array{Painter, FakeExecutor} */
function textPainter(?ExecutorCapabilities $caps = null): array
{
    $executor = new FakeExecutor($caps);
    $executor->resize(200, 100);
    $painter = new Painter($executor);
    $painter->begin(200, 100, 1.0);

    return [$painter, $executor];
}

it('draws a string as one textured batch, six tinted vertices per glyph, UVs from the atlas', function () {
    [$g, $x] = textPainter(new ExecutorCapabilities(blending: true, depth: false, instancing: true, readback: true, max_texture_size: 64));
    $g->text('AB', 10.0, 20.0, new Color(1.0, 0.0, 0.0, 1.0), new TinyFace())->flush();

    expect($x->draws)->toHaveCount(1)
        ->and($x->draws[0]['topology'])->toBe(Topology::TRIANGLES)
        ->and($x->draws[0]['count'])->toBe(12)
        ->and($x->draws[0]['texture'])->not->toBeNull()
        ->and($x->textures)->toHaveCount(1);

    $id = array_key_first($x->textures);
    [, $aw, $ah] = $x->textures[$id];
    expect([$aw, $ah])->toBe([64, 8])->and($x->draws[0]['texture']->id)->toBe($id);

    $rows = $x->vertices(0);
    expect($rows[0])->toBe([10.0, 20.0, 0.0, 1.0, 0.0, 0.0, 1.0, 1 / 64, 1 / 8])   // A top-left → atlas (1,1)
        ->and($rows[2])->toBe([13.0, 23.0, 0.0, 1.0, 0.0, 0.0, 1.0, 4 / 64, 4 / 8]) // A bottom-right → atlas (4,4)
        ->and($rows[6][0])->toBe(14.0)                                              // B starts one advance (4) later
        ->and($rows[6][7])->toBe(5 / 64);                                           // B's atlas column
});

it('bakes the atlas once per face class and keeps it across frames until released', function () {
    [$g, $x] = textPainter();
    $g->text('A', 0.0, 0.0, Color::hex('#fff'), new TinyFace())->flush();
    $g->begin(200, 100, 1.0);
    $g->text('B', 0.0, 0.0, Color::hex('#fff'), new TinyFace())->flush();

    expect($x->textures)->toHaveCount(1)->and($x->released_textures)->toBe([]);

    $g->releaseAtlases();
    expect($x->released_textures)->toHaveCount(1);

    $g->text('C', 0.0, 0.0, Color::hex('#fff'), new TinyFace())->flush();
    expect($x->textures)->toHaveCount(1)->and($x->draws)->toHaveCount(3);
});

it('goes through the stack and the backing scale like image()', function () {
    $executor = new FakeExecutor();
    $executor->resize(400, 200);
    $g = new Painter($executor);
    $g->begin(200, 100, 2.0);
    $g->push()->translate(5.0, 5.0)->text('A', 1.0, 1.0, Color::hex('#fff'), new TinyFace())->pop()->flush();

    expect($executor->vertices(0)[0][0])->toBe(12.0)->and($executor->vertices(0)[0][1])->toBe(12.0);
});

it('answers textBounds from the typesetter and draws nothing for a string without ink', function () {
    [$g, $x] = textPainter();

    expect($g->textBounds("AB\nC", new TinyFace()))->toBe([0.0, 0.0, 7.0, 12.0]);

    $g->text("Z\n", 0.0, 0.0, Color::hex('#fff'), new TinyFace())->flush();
    expect($x->draws)->toBe([]);
});

it('drops alpha on an executor that cannot blend', function () {
    [$g, $x] = textPainter(new ExecutorCapabilities(blending: false, depth: false, instancing: false, readback: false, max_texture_size: 256));
    $g->text('A', 0.0, 0.0, new Color(0.0, 1.0, 0.0, 0.25), new TinyFace())->flush();

    expect($x->vertices(0)[0][6])->toBe(1.0);
});
