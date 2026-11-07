<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\Filter;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Contracts\Framebuffers\Spans;
use Surface\NutsAndBolts\Affine;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\Gradient;
use Surface\NutsAndBolts\GradientSpread;
use Surface\NutsAndBolts\LinearGradient;
use Venusian\Surface\Tests\Support\Framebuffers\Rgba8Source;

/*
 * paintGradient() on every framebuffer driver: the bytes are those of the plain way, a position worked out in full for
 * every pixel (a multiply per axis) and the colours laid through paintImage() with the spans as its mask.
 */

function gradientRamp(): string
{
    return (new LinearGradient(0.0, 0.0, 1.0, 0.0, [[0.0, Color::rgb(255, 0, 0)], [0.5, Color::rgba(0, 255, 0, 0.5)], [1.0, Color::rgb(0, 0, 255)]]))->ramp();
}

/** @return list<float> a, b, c, d, e, f */
function gradientInverse(float $angle, float $scale, float $dx, float $dy): array
{
    $c = cos($angle) * $scale;
    $s = sin($angle) * $scale;

    return [$c, -$s, $s, $c, $dx, $dy];
}

function plainGradient(FramebufferDriver $driver, int $w, int $h, string $spans, string $ramp, string $kind, GradientSpread $spread, array $m): string
{
    $buffer = $driver->full(FormatSpec::rgba8(), $w, $h);
    $buffer->fill(0x336699ff);
    $bytes = str_repeat("\0", $w * $h * 4);
    $mask = '';
    foreach (Spans::unpack($spans) as [$y, $x, $length, $cover]) {
        for ($column = $x; $column < $x + $length; $column++) {
            $u = $m[0] * ($column + 0.5) + $m[2] * ($y + 0.5) + $m[4];
            $v = $m[1] * ($column + 0.5) + $m[3] * ($y + 0.5) + $m[5];
            $at = (Gradient::index(Gradient::at($kind, $spread, $u, $v)));
            $bytes = substr_replace($bytes, substr($ramp, $at * 4, 4), ($y * $w + $column) * 4, 4);
        }
    }
    $buffer->paintImage(new Rgba8Source($bytes, $w, $h), Affine::identity(), 255, Filter::NEAREST, null, 0xFFFFFFFF, null, $spans);

    return bin2hex($buffer->dump());
}

it('paints what the plain per-pixel way paints', function (FramebufferDriver $driver, string $kind, GradientSpread $spread, array $m): void {
    $spans = Spans::pack(0, 1, 30, 255).Spans::pack(1, 0, 20, 128).Spans::pack(1, 25, 7, 255).Spans::pack(5, 3, 29, 200).Spans::pack(9, 0, 32, 255);
    $buffer = $driver->full(FormatSpec::rgba8(), 32, 10);
    $buffer->fill(0x336699ff);
    $buffer->paintGradient($spans, gradientRamp(), $kind, $spread, $m);

    expect(bin2hex($buffer->dump()))->toBe(plainGradient($driver, 32, 10, $spans, gradientRamp(), $kind, $spread, $m));
})->with('framebuffer drivers')->with([
    'linear pad' => ['linear', GradientSpread::PAD, [1 / 32, 0.0, 0.0, 1 / 32, 0.0, 0.0]],
    'linear repeat, turned' => ['linear', GradientSpread::REPEAT, gradientInverse(0.4, 0.1, -0.3, 0.2)],
    'linear reflect, turned' => ['linear', GradientSpread::REFLECT, gradientInverse(-1.1, 0.07, 0.6, -0.25)],
    'radial pad' => ['radial', GradientSpread::PAD, gradientInverse(0.0, 0.05, -0.8, -0.4)],
    'radial reflect, stretched' => ['radial', GradientSpread::REFLECT, [0.09, 0.02, -0.03, 0.14, -1.2, -0.7]],
    'radial repeat' => ['radial', GradientSpread::REPEAT, gradientInverse(0.9, 0.11, -1.0, -0.1)],
]);

it('paints nothing for no spans, and refuses a ramp of the wrong size or a bad inverse', function (FramebufferDriver $driver): void {
    $buffer = $driver->full(FormatSpec::rgba8(), 2, 1);
    $buffer->paintGradient('', gradientRamp(), 'linear', GradientSpread::PAD, [1.0, 0.0, 0.0, 1.0, 0.0, 0.0]);
    expect(bin2hex($buffer->dump()))->toBe('0000000000000000');

    $spans = Spans::pack(0, 0, 2, 255);
    expect(fn () => $buffer->paintGradient($spans, 'short', 'linear', GradientSpread::PAD, [1.0, 0.0, 0.0, 1.0, 0.0, 0.0]))->toThrow(FramebufferException::class)
        ->and(fn () => $buffer->paintGradient($spans, gradientRamp(), 'linear', GradientSpread::PAD, [1.0, 0.0, 0.0, 1.0, 0.0]))->toThrow(FramebufferException::class)
        ->and(fn () => $buffer->paintGradient(Spans::pack(0, 1, 5, 255), gradientRamp(), 'linear', GradientSpread::PAD, [1.0, 0.0, 0.0, 1.0, 0.0, 0.0]))->toThrow(FramebufferException::class);
})->with('framebuffer drivers');
