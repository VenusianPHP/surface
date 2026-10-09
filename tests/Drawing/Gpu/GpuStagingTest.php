<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\ColorSpace;
use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Drawing\PresentTiming;
use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Drawing\SurfaceKind;
use Surface\Contracts\Drawing\TargetFormat;
use Surface\Contracts\Drawing\VSync;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Windows\ScaleFilter;
use Surface\Contracts\Windows\ScaleFit;
use Surface\Drawing\Gpu\GpuRenderingEngine;
use Surface\NutsAndBolts\Color;
use Venusian\Surface\Tests\Fixtures\FakeSession;

/*
 * A GPU engine drawing for a staged window: what the window asks of the
 * device through the surface it lends, over FakeCapableGpuDevice.
 */

/** A staged window of 320x240 points (640x480 pixels) that lends a Metal layer. */
function gpuStage(array $options = []): FakeStagedWindow
{
    /** @var FakeStagedWindow $window */
    $window = (new FakeStager(new FakeSession))->openStaged('stage', 320, 240, $options);
    $window->lends = [SurfaceKind::METAL_LAYER];

    return $window;
}

/** @return list<string> The device's vsync calls, in order. */
function vsyncCalls(FakeCapableGpuDevice $device): array
{
    return array_values(array_filter($device->inner->log, fn (string $line): bool => str_starts_with($line, 'vsync')));
}

it('presents with the window vsync and follows its changes, falling back to on', function (): void {
    $window = gpuStage(['vsync' => VSync::Off]);
    $device = new FakeCapableGpuDevice;
    $engine = GpuRenderingEngine::from($device, ['output' => $window], drawing());

    $first = $engine->vsync();
    $window->setVsync(VSync::Mailbox);
    $fallen = $engine->vsync();
    $window->setVsync(VSync::On);

    expect($first)->toBe(VSync::Off)
        ->and($fallen)->toBe(VSync::On)
        ->and($engine->vsync())->toBe(VSync::On)
        ->and(array_slice($device->inner->log, 0, 3))->toBe(['adopt', 'vsync off', 'target 640x480x4'])
        ->and(vsyncCalls($device))->toBe(['vsync off', 'vsync on']);
});

it('leaves the device alone once the window has its surface back', function (): void {
    $window = gpuStage();
    $device = new FakeCapableGpuDevice;
    $engine = GpuRenderingEngine::from($device, ['output' => $window], drawing());

    $engine->release();
    $window->setVsync(VSync::Off);

    expect($engine->vsync())->toBe(VSync::On)
        ->and(vsyncCalls($device))->toBe(['vsync on']);
});

it('picks no vsync for a device that keeps its own, or offscreen', function (): void {
    expect(GpuRenderingEngine::from(new FakeGpuDevice([SurfaceKind::METAL_LAYER]), ['output' => gpuStage()], drawing())->vsync())->toBeNull()
        ->and((new GpuRenderingEngine(new FakeCapableGpuDevice, 64, 32))->vsync())->toBeNull();
});

it('refuses a device that cannot present with vsync on, giving the surface back', function (): void {
    $window = gpuStage();
    $device = new FakeCapableGpuDevice;
    $device->modes = [VSync::Off];

    expect(fn () => GpuRenderingEngine::from($device, ['output' => $window], drawing()))
        ->toThrow(DrawingException::class, 'capable lists no vsync it can present with: it lists off; every device presents with on.')
        ->and($window->lent())->toBeNull();
});

it('makes an HDR target the device offers for a window, its colour space defaulting to the format', function (): void {
    $device = new FakeCapableGpuDevice;
    $engine = GpuRenderingEngine::from($device, ['output' => gpuStage(), 'format' => TargetFormat::Rgba16Float], drawing());

    expect([$engine->format(), $engine->colorspace()])->toBe([TargetFormat::Rgba16Float, ColorSpace::ExtendedLinearSrgb])
        ->and($device->inner->log)->toContain('target 640x480x4 rgba16float extended-linear-srgb');
});

it('keeps the format when a resize remakes the target', function (): void {
    $window = gpuStage();
    $device = new FakeCapableGpuDevice;
    $engine = GpuRenderingEngine::from($device, ['output' => $window, 'format' => TargetFormat::Rgb10A2, 'colorspace' => ColorSpace::Hdr10Pq], drawing());

    $window->measures = [400, 300];
    $engine->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0)));

    expect($device->inner->log)->toContain('target 800x600x4 rgb10a2 hdr10-pq');
});

it('makes an RGBA8 sRGB target through target(), as before', function (): void {
    $device = new FakeCapableGpuDevice;
    $engine = new GpuRenderingEngine($device, 8, 8);

    expect([$engine->format(), $engine->colorspace()])->toBe([TargetFormat::Rgba8, ColorSpace::Srgb])
        ->and($device->inner->log)->toBe(['target 8x8x4']);
});

it('reads an HDR target back as an HdrImage', function (): void {
    $engine = new GpuRenderingEngine(new FakeCapableGpuDevice, 2, 1, format: TargetFormat::Rgba16Float, colorspace: ColorSpace::ExtendedLinearSrgb);

    expect($engine->hdrSnapshot()->rgba16f())->toBe(str_repeat(pack('v4', 0x4000, 0x3C00, 0x0000, 0x3C00), 2))
        ->and(fn () => (new GpuRenderingEngine(new FakeGpuDevice, 2, 1))->hdrSnapshot())
        ->toThrow(DrawingException::class, "fake's target reads back no float pixels: read it as RGBA8 through framebuffer().");
});

it('refuses a target it cannot make, before touching the device', function (Closure $args, string $message): void {
    $device = new FakeCapableGpuDevice;

    expect(fn () => GpuRenderingEngine::from($device, $args(), drawing()))->toThrow(DrawingException::class, $message)
        ->and($device->inner->log)->toBe([]);
})->with([
    'not a format' => [fn (): array => ['width' => 8, 'height' => 8, 'format' => 'rgba16f'], "'format' is a TargetFormat: rgba8, rgb10a2, rgba16float."],
    'not a colour space' => [fn (): array => ['width' => 8, 'height' => 8, 'colorspace' => 'p3'], "'colorspace' is a ColorSpace: srgb, display-p3, extended-linear-srgb, extended-linear-display-p3, hdr10-pq."],
    'a pair no format carries' => [fn (): array => ['width' => 8, 'height' => 8, 'format' => TargetFormat::Rgba8, 'colorspace' => ColorSpace::Hdr10Pq], 'rgba8 carries srgb, display-p3; not hdr10-pq.'],
    'one the device does not make' => [fn (): array => ['width' => 8, 'height' => 8, 'colorspace' => ColorSpace::DisplayP3], 'capable makes no rgba8 display-p3 target. It makes: rgba8 srgb, rgba16float extended-linear-srgb, rgb10a2 hdr10-pq.'],
    'for a display' => [fn (): array => ['output' => fakeDisplay(), 'format' => TargetFormat::Rgba16Float], "A display shows RGBA8 sRGB: 'format' and 'colorspace' are for a window or an offscreen target."],
]);

it('refuses a format of a device that makes only RGBA8 sRGB', function (): void {
    expect(fn () => new GpuRenderingEngine(new FakeGpuDevice, 8, 8, format: TargetFormat::Rgba16Float, colorspace: ColorSpace::ExtendedLinearSrgb))
        ->toThrow(DrawingException::class, 'fake makes no rgba16float extended-linear-srgb target. It makes: rgba8 srgb.');
});

it('bounds the frames in flight after adopting the surface, and reports and waits on presents', function (): void {
    $window = gpuStage();
    $device = new FakeCapableGpuDevice;
    $engine = GpuRenderingEngine::from($device, ['output' => $window, 'frames_in_flight' => 3], drawing());
    $engine->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0)));
    $window->present();
    $device->shown = new PresentTiming(1, 5_000, 16_666_667);

    expect(array_slice($device->inner->log, 0, 4))->toBe(['adopt', 'frames 3', 'vsync on', 'target 640x480x4'])
        ->and($engine->framesInFlight())->toBe(3)
        ->and($engine->submitted())->toBe(1)
        ->and($engine->presents())->toBe($device->shown)
        ->and($engine->waitPresented(1, 2_000_000))->toBeTrue()
        ->and($device->waits)->toBe([[1, 2_000_000]]);
});

it('bounds the frames in flight of an offscreen engine before its target', function (): void {
    $device = new FakeCapableGpuDevice;
    $engine = new GpuRenderingEngine($device, 8, 8, framesInFlight: 2);

    expect($device->inner->log)->toBe(['frames 2', 'target 8x8x4'])
        ->and($engine->framesInFlight())->toBe(2);
});

it("leaves the device's own frames in flight and reports nothing for a device that does not", function (): void {
    $engine = new GpuRenderingEngine(new FakeGpuDevice, 8, 8);

    expect([$engine->framesInFlight(), $engine->submitted(), $engine->presents()])->toBe([null, null, null])
        ->and(fn () => $engine->waitPresented(1, 0))->toThrow(DrawingException::class, 'fake reports no presents: presents() is null for it.');
});

it('refuses frames in flight it cannot bound, before touching the device', function (): void {
    $plain = new FakeGpuDevice([SurfaceKind::METAL_LAYER]);
    $capable = new FakeCapableGpuDevice;

    expect(fn () => GpuRenderingEngine::from($capable, ['width' => 8, 'height' => 8, 'frames_in_flight' => 4], drawing()))
        ->toThrow(DrawingException::class, "'frames_in_flight' is 1, 2 or 3.")
        ->and(fn () => GpuRenderingEngine::from($plain, ['output' => gpuStage(), 'frames_in_flight' => 2], drawing()))
        ->toThrow(DrawingException::class, "fake does not bound frames in flight: leave 'frames_in_flight' out for it.")
        ->and([$plain->log, $capable->inner->log])->toBe([[], []]);
});

it('waits on a frame of at least 1 for a timeout of at least 0', function (): void {
    $engine = new GpuRenderingEngine(new FakeCapableGpuDevice, 8, 8);

    expect(fn () => $engine->waitPresented(0, 1))
        ->toThrow(DrawingException::class, 'waitPresented() takes a frame of at least 1 and a timeout of at least 0 ns, got 0 and 1.');
});

it('renders a window at a fixed resolution, keeping it through resizes and repainting all of it', function (): void {
    $window = gpuStage();
    $device = new FakeCapableGpuDevice;
    $engine = GpuRenderingEngine::from($device, ['output' => $window, 'resolution' => [320, 200]], drawing());
    $engine->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0)));
    $engine->framebuffer()->beginEpoch();

    $window->measures = [400, 300];
    $window->setScaling(ScaleFilter::Nearest, ScaleFit::Integer);
    $engine->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0)));

    expect([$engine->width(), $engine->height()])->toBe([320, 200])
        ->and(array_values(array_filter($device->inner->log, fn (string $line): bool => str_starts_with($line, 'target'))))->toBe(['target 320x200x4'])
        ->and($engine->framebuffer()->damage())->toEqual([new Region(0, 0, 320, 200)])
        ->and($window->lent()->presentRect(320, 200))->toEqual(new Region(80, 100, 640, 400));
});

it('refuses a resolution that is not a size, or not for a window', function (Closure $args, string $message): void {
    $device = new FakeCapableGpuDevice;

    expect(fn () => GpuRenderingEngine::from($device, $args(), drawing()))->toThrow(DrawingException::class, $message)
        ->and($device->inner->log)->toBe([]);
})->with([
    'one number' => [fn (): array => ['output' => gpuStage(), 'resolution' => [320]], "'resolution' is [width, height], each an integer of at least 1."],
    'a zero' => [fn (): array => ['output' => gpuStage(), 'resolution' => [320, 0]], "'resolution' is [width, height], each an integer of at least 1."],
    'offscreen' => [fn (): array => ['width' => 8, 'height' => 8, 'resolution' => [4, 4]], "'resolution' renders a window's frames at a fixed size: give it with a window's 'output'."],
    'for a display' => [fn (): array => ['output' => fakeDisplay(), 'resolution' => [4, 4]], "'resolution' renders a window's frames at a fixed size: give it with a window's 'output'."],
]);
