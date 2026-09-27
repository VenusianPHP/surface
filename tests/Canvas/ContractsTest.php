<?php

use Surface\Contracts\Canvas\Canvasable;
use Surface\Contracts\Canvas\CanvasException;
use Surface\Contracts\Canvas\CanvasKind;
use Surface\Contracts\Core\SurfaceLevelException;
use Surface\Contracts\Drawing\Drawing2D;
use Surface\Contracts\Stage\StagedWindow;

it('is a lifecycle over an output, not a Drawing2D of its own', function () {
    expect(is_subclass_of(Canvasable::class, Drawing2D::class))->toBeFalse();

    foreach (['output', 'kind', 'drawing', 'draw', 'present', 'animate', 'background', 'width', 'height', 'size', 'show', 'hide', 'isVisible', 'close', 'isOpen'] as $verb) {
        expect(method_exists(Canvasable::class, $verb))->toBeTrue();
    }

    // Ink is legal only inside the frame, so no drawing verb is on the canvas.
    foreach (['clear', 'fillRect', 'fillCircle', 'text', 'image', 'push', 'translate', 'clip'] as $verb) {
        expect(method_exists(Canvasable::class, $verb))->toBeFalse();
    }
});

it('names the four outputs a canvas can wrap', function () {
    expect(array_map(fn (CanvasKind $k) => $k->value, CanvasKind::cases()))
        ->toBe(['gpu-view', 'gpu-stage', 'cpu-stage', 'embedded-display']);
});

it('roots its exception in SurfaceLevelException', function () {
    expect(CanvasException::closed())->toBeInstanceOf(SurfaceLevelException::class)
        ->and(CanvasException::unsupported('hide', CanvasKind::GPU_STAGE)->getMessage())->toBe("A 'gpu-stage' canvas cannot hide().")
        ->and(CanvasException::unsupportedOutput('stdClass')->getMessage())->toContain('stdClass');
});

it('lets a stage say whether it has been shown', function () {
    expect(method_exists(StagedWindow::class, 'isShown'))->toBeTrue();
});
