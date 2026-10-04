<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Framebuffers\Extended\ExtendedFramebufferDriver;
use Surface\Framebuffers\Native\NativeFramebufferDriver;

it('defaults to the native driver and builds it once', function (): void {
    $manager = framebuffers();

    expect($manager->getDefaultDriver())->toBe('native')
        ->and($manager->driver())->toBeInstanceOf(NativeFramebufferDriver::class)
        ->and($manager->driver('native'))->toBe($manager->driver());
});

it('takes its default from config', function (): void {
    expect(framebuffers(['framebuffers.default' => 'extended'])->getDefaultDriver())->toBe('extended');
});

it('builds the extended driver when ext-fb is loaded', function (): void {
    expect(framebuffers()->driver('extended'))->toBeInstanceOf(ExtendedFramebufferDriver::class)
        ->and(framebuffers()->driver('extended')->driver())->toBe('extended');
})->skip(! class_exists(FbBuffer::class), 'ext-fb 0.10 is not loaded in this PHP.');

it('refuses the extended driver without ext-fb 0.10, naming the extension and the PHP', function (): void {
    expect(fn () => framebuffers()->driver('extended'))->toThrow(FramebufferException::class, 'needs ext-fb 0.10 or newer loaded in this PHP ('.PHP_BINARY.')');
})->skip(class_exists(FbBuffer::class), 'ext-fb 0.10 is loaded in this PHP.');

it('refuses a driver it does not have', function (): void {
    expect(fn () => framebuffers()->driver('gpu'))->toThrow(InvalidArgumentException::class, 'Driver [gpu] not supported');
});
