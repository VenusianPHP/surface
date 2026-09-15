<?php

use Surface\Contracts\Drawing\Drawing2D;
use Surface\Contracts\Drawing\Executor;
use Surface\Contracts\Drawing\ExecutorCapabilities;
use Surface\Contracts\Drawing\Frame;
use Surface\Contracts\Drawing\GPUEngine;
use Surface\Contracts\Drawing\TextureHandle;
use Surface\Contracts\Drawing\Topology;
use Surface\Contracts\Drawing\Transform;
use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Contracts\Stage\Events\StageClosed;
use Surface\Contracts\Stage\Events\StageResized;
use Surface\Contracts\Stage\StageException;
use Venusian\Surface\Tests\Support\Fakes\FakeExecutor;
use Venusian\Surface\Tests\Support\Fakes\FakeStagedWindow;

/** @return array{FakeStagedWindow, FakeExecutor, \Voyager\IOPools\IOPoolDock} */
function stagedWindow(float $scale = 1.0): array
{
    $executor = new FakeExecutor();
    $dock = bareDock();
    $stage = (new FakeStagedWindow('main', GPUEngine::METAL, $executor, 640, 480, $scale))->setPool($dock);

    return [$stage, $executor, $dock];
}

it('knows its name, engine, size and scale, and starts open', function () {
    [$stage] = stagedWindow(2.0);

    expect($stage->name())->toBe('main')
        ->and($stage->engine())->toBe(GPUEngine::METAL)
        ->and($stage->size())->toBe([640, 480])
        ->and($stage->scale())->toBe(2.0)
        ->and($stage->isOpen())->toBeTrue();
});

it('writes the title and shows through the host', function () {
    [$stage] = stagedWindow();
    $stage->setTitle('Orbit')->show();

    expect($stage->title())->toBe('Orbit')
        ->and($stage->log)->toBe(['title:Orbit', 'show']);
});

it('resized() resizes the executor in pixels and mails once per change', function () {
    [$stage, $executor, $dock] = stagedWindow();
    $stage->resized(800, 600, 2.0);
    $stage->resized(800, 600, 2.0);
    $mail = $dock->drain();

    expect($executor->size)->toBe([1600, 1200])
        ->and($stage->size())->toBe([800, 600])
        ->and($stage->scale())->toBe(2.0)
        ->and($mail)->toHaveCount(1)
        ->and($mail->first())->toBeInstanceOf(StageResized::class)
        ->and($mail->first()->name)->toBe('stage.resized.main')
        ->and($mail->first()->width)->toBe(800);
});

it('a real resize asks a shown, on-demand stage for one frame', function () {
    [$stage] = stagedWindow();
    $widths = [];
    $stage->show()->setContinuous(false)->onDraw(function (Drawing2D $g, Frame $frame) use (&$widths) {
        $widths[] = $frame->width;
    });

    $idle = $stage->renderFrame();
    $stage->resized(800, 600, 1.0);
    $after_resize = $stage->renderFrame();
    $stage->resized(800, 600, 1.0);
    $after_same_size = $stage->renderFrame();

    expect([$idle, $after_resize, $after_same_size])->toBe([false, true, false])
        ->and($widths)->toBe([800]);
});

it('closeRequested() mails once and leaves the stage open', function () {
    [$stage, , $dock] = stagedWindow();
    $stage->closeRequested();
    $stage->closeRequested();
    $mail = $dock->drain();

    expect($mail)->toHaveCount(1)
        ->and($mail->first())->toBeInstanceOf(StageClosed::class)
        ->and($mail->first()->name)->toBe('stage.closed.main')
        ->and($stage->isOpen())->toBeTrue();
});

it('a close request with no pool yet is not spent: the next one mails once', function () {
    $stage = new FakeStagedWindow('main', GPUEngine::METAL, new FakeExecutor(), 640, 480);
    $stage->closeRequested();

    $dock = bareDock();
    $stage->setPool($dock);
    $stage->closeRequested();
    $stage->closeRequested();

    expect($dock->drain())->toHaveCount(1);
});

it('close() releases the engine before the native, mails once, and is idempotent', function () {
    [$stage, $executor, $dock] = stagedWindow();
    $stage->close();
    $stage->close();

    expect($stage->isOpen())->toBeFalse()
        ->and($executor->released)->toBeTrue()
        ->and($stage->log)->toBe(['destroyNative:after-release'])
        ->and($dock->drain())->toHaveCount(1);
});

it('close() still destroys the native and announces when the engine release throws', function () {
    $executor = new class implements Executor
    {
        public function capabilities(): ExecutorCapabilities
        {
            return new ExecutorCapabilities(blending: true, depth: false, instancing: true, readback: false, max_texture_size: 4096);
        }

        public function resize(int $width, int $height): void {}

        public function drawableSize(): array
        {
            return [0, 0];
        }

        public function beginFrame(Color $clear): bool
        {
            return false;
        }

        public function viewport(int $x, int $y, int $width, int $height): void {}

        public function scissor(int $x, int $y, int $width, int $height): void {}

        public function unscissor(): void {}

        public function texture(string $rgba8, int $width, int $height): TextureHandle
        {
            return new TextureHandle(1, $width, $height);
        }

        public function releaseTexture(TextureHandle $texture): void {}

        public function draw(Topology $topology, string $vertices, int $vertex_count, Transform $transform, ?TextureHandle $texture = null, int $instances = 1): void {}

        public function drawIndexed(Topology $topology, string $vertices, int $vertex_count, string $indices, int $index_count, Transform $transform, ?TextureHandle $texture = null, int $instances = 1): void {}

        public function readPixels(): string
        {
            return '';
        }

        public function endFrame(): void {}

        public function release(): void
        {
            throw new RuntimeException('release failed');
        }
    };
    $dock = bareDock();
    $stage = (new FakeStagedWindow('main', GPUEngine::METAL, $executor, 640, 480))->setPool($dock);

    expect(fn () => $stage->close())->toThrow(RuntimeException::class, 'release failed');
    $stage->close();

    expect($stage->isOpen())->toBeFalse()
        ->and($stage->log)->toHaveCount(1)
        ->and($stage->log[0])->toStartWith('destroyNative:')
        ->and($dock->drain())->toHaveCount(1);
});

it('a close after a close request does not mail twice', function () {
    [$stage, , $dock] = stagedWindow();
    $stage->closeRequested();
    $stage->close();

    expect($dock->drain())->toHaveCount(1);
});

it('refuses host writes after close', function () {
    [$stage] = stagedWindow();
    $stage->close();

    expect(fn () => $stage->setTitle('late'))->toThrow(StageException::class, "Stage 'main' is closed.")
        ->and(fn () => $stage->show())->toThrow(StageException::class);
});

it('a hidden stage renders nothing and begins no frame', function () {
    [$stage, $executor] = stagedWindow();
    $stage->onDraw(fn (Drawing2D $g, Frame $frame) => null);

    expect($stage->renderFrame())->toBeFalse()
        ->and($executor->frames_begun)->toBe(0)
        ->and($executor->calls)->not->toContain('beginFrame');
});

it('renders with its own size and scale once shown, never once closed', function () {
    [$stage] = stagedWindow(2.0);
    $stage->show();
    $seen = null;
    $stage->onDraw(function (Drawing2D $g, Frame $frame) use (&$seen) {
        $seen = $frame;
    });

    expect($stage->renderFrame())->toBeTrue()
        ->and($seen->width)->toBe(640)
        ->and($seen->scale)->toBe(2.0);

    $stage->close();
    expect($stage->renderFrame())->toBeFalse();
});
