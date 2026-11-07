<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Framebuffers\Extended\ExtendedFramebufferDriver;
use Surface\Framebuffers\Native\NativeFramebufferDriver;

it('defaults to auto: extended when ext-fb 0.11.1 is loaded, native when not, built once', function (): void {
    $manager = framebuffers();

    expect($manager->getDefaultDriver())->toBe('auto')
        ->and($manager->driver())->toBeInstanceOf(ExtendedFramebufferDriver::available() ? ExtendedFramebufferDriver::class : NativeFramebufferDriver::class)
        ->and($manager->driver('auto'))->toBe($manager->driver());
});

it('builds native when named, whatever is loaded', function (): void {
    expect(framebuffers()->driver('native'))->toBeInstanceOf(NativeFramebufferDriver::class)
        ->and(framebuffers(['framebuffers.default' => 'native'])->driver()->driver())->toBe('native');
});

it('takes its default from config', function (): void {
    expect(framebuffers(['framebuffers.default' => 'extended'])->getDefaultDriver())->toBe('extended');
});

it('builds the extended driver when ext-fb is loaded', function (): void {
    expect(framebuffers()->driver('extended'))->toBeInstanceOf(ExtendedFramebufferDriver::class)
        ->and(framebuffers()->driver('extended')->driver())->toBe('extended');
})->skip(! ExtendedFramebufferDriver::available(), 'ext-fb 0.11.1 is not loaded in this PHP.');

it('refuses the extended driver without ext-fb 0.11.1, naming the extension and the PHP', function (): void {
    expect(fn () => framebuffers()->driver('extended'))->toThrow(FramebufferException::class, 'needs ext-fb 0.11.1 or newer loaded in this PHP ('.PHP_BINARY.')');
})->skip(ExtendedFramebufferDriver::available(), 'ext-fb 0.11.1 is loaded in this PHP.');

it('refuses a driver it does not have', function (): void {
    expect(fn () => framebuffers()->driver('gpu'))->toThrow(InvalidArgumentException::class, 'Driver [gpu] not supported');
});
