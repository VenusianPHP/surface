<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\DmabufExportable;

it('is the contract a framebuffer exporting a dmabuf answers, null when it is not exporting', function (): void {
    expect(interface_exists(DmabufExportable::class))->toBeTrue()
        ->and((new ReflectionMethod(DmabufExportable::class, 'dmabuf'))->getReturnType()?->allowsNull())->toBeTrue();
});

it('takes the export back from the importer by its fd', function (): void {
    $release = new ReflectionMethod(DmabufExportable::class, 'releaseDmabuf');

    expect($release->getNumberOfParameters())->toBe(1)
        ->and((string) $release->getParameters()[0]->getType())->toBe('int')
        ->and((string) $release->getReturnType())->toBe('void');
});
