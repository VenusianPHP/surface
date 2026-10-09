<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\ColorSpace;
use Surface\Contracts\Drawing\PresentTiming;
use Surface\Contracts\Drawing\TargetFormat;
use Surface\Contracts\Drawing\VSync;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Windows\ScaleFit;

/*
 * The values staged frames are described with: vsync fallbacks, target
 * formats and their colour spaces, a present's timing, where a target lands.
 */

it('falls back to on from every vsync, and on stands alone', function (VSync $wanted, array $order): void {
    expect($wanted->fallbacks())->toBe($order);
})->with([
    'off' => [VSync::Off, [VSync::Off, VSync::On]],
    'on' => [VSync::On, [VSync::On]],
    'adaptive' => [VSync::Adaptive, [VSync::Adaptive, VSync::On]],
    'mailbox' => [VSync::Mailbox, [VSync::Mailbox, VSync::On]],
]);

it('pairs each target format with the colour spaces it carries, its default first', function (): void {
    expect(TargetFormat::Rgba8->colorSpaces())->toBe([ColorSpace::Srgb, ColorSpace::DisplayP3])
        ->and(TargetFormat::Rgb10A2->colorSpaces())->toBe([ColorSpace::Srgb, ColorSpace::DisplayP3, ColorSpace::Hdr10Pq])
        ->and(TargetFormat::Rgba16Float->colorSpaces())->toBe([ColorSpace::ExtendedLinearSrgb, ColorSpace::ExtendedLinearDisplayP3]);
});

it('names the colour spaces that reach past SDR white', function (): void {
    expect(array_values(array_filter(ColorSpace::cases(), fn (ColorSpace $space): bool => $space->isHdr())))
        ->toBe([ColorSpace::ExtendedLinearSrgb, ColorSpace::ExtendedLinearDisplayP3, ColorSpace::Hdr10Pq]);
});

it("carries a present's frame, when it was shown and the refresh, unknowns null", function (): void {
    $known = new PresentTiming(7, 1_000_000, 16_666_667);
    $unknown = new PresentTiming(8);

    expect([$known->frame, $known->presentedAt, $known->refreshInterval])->toBe([7, 1_000_000, 16_666_667])
        ->and([$unknown->frame, $unknown->presentedAt, $unknown->refreshInterval])->toBe([8, null, null]);
});

it('places a target in a surface by the fit', function (ScaleFit $fit, int $width, int $height, Region $rect): void {
    expect($fit->rect(640, 480, $width, $height))->toEqual($rect);
})->with([
    'stretch' => [ScaleFit::Stretch, 320, 200, new Region(0, 0, 640, 480)],
    'letterbox' => [ScaleFit::Letterbox, 320, 200, new Region(0, 40, 640, 400)],
    'integer' => [ScaleFit::Integer, 300, 200, new Region(20, 40, 600, 400)],
    'integer, larger than the surface' => [ScaleFit::Integer, 1280, 800, new Region(0, 40, 640, 400)],
    'an empty target' => [ScaleFit::Letterbox, 0, 0, new Region(0, 0, 640, 480)],
]);
