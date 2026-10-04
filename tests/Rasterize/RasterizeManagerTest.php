<?php

declare(strict_types=1);

use Surface\Contracts\Rasterize\RasterizeException;
use Surface\Rasterize\Extended\ExtendedRasterizeDriver;
use Surface\Rasterize\Native\NativeRasterizeDriver;

it('defaults to auto: extended when ext-rasterize is loaded, native when not, built once', function (): void {
    $manager = rasterize();

    expect($manager->getDefaultDriver())->toBe('auto')
        ->and($manager->driver())->toBeInstanceOf(class_exists(RasterScanner::class) ? ExtendedRasterizeDriver::class : NativeRasterizeDriver::class)
        ->and($manager->driver('auto'))->toBe($manager->driver())
        ->and($manager->driver()->driver())->toBe(class_exists(RasterScanner::class) ? 'extended' : 'native');
});

it('builds native when named, whatever is loaded', function (): void {
    expect(rasterize()->driver('native'))->toBeInstanceOf(NativeRasterizeDriver::class)
        ->and(rasterize(['rasterize.default' => 'native'])->driver()->driver())->toBe('native');
});

it('takes its default from config', function (): void {
    expect(rasterize(['rasterize.default' => 'extended'])->getDefaultDriver())->toBe('extended');
});

it('builds the extended driver when ext-rasterize is loaded', function (): void {
    expect(rasterize()->driver('extended'))->toBeInstanceOf(ExtendedRasterizeDriver::class)
        ->and(rasterize()->driver('extended')->driver())->toBe('extended');
})->skip(! class_exists(RasterScanner::class), 'ext-rasterize is not loaded in this PHP.');

it('refuses the extended driver without ext-rasterize, naming the extension and the PHP', function (): void {
    expect(fn () => rasterize()->driver('extended'))->toThrow(RasterizeException::class, 'needs ext-rasterize 0.10 or newer loaded in this PHP ('.PHP_BINARY.')');
})->skip(class_exists(RasterScanner::class), 'ext-rasterize is loaded in this PHP.');

it('refuses a driver it does not have', function (): void {
    expect(fn () => rasterize()->driver('gpu'))->toThrow(InvalidArgumentException::class, 'Driver [gpu] not supported');
});
