<?php

use Surface\Contracts\Drawing\Drawing2D;
use Surface\Contracts\Drawing\Executor;
use Surface\Contracts\Drawing\Frame;
use Surface\Drawing\Concerns\RunsFrames;
use Surface\Drawing\Painter;
use Venusian\Surface\Tests\Support\Fakes\FakeExecutor;

/** A frame target that is not a View — proof the loop has no View dependency. */
function frameTarget(FakeExecutor $executor, array $size = [100, 50], float $scale = 2.0): object
{
    return new class($executor, $size, $scale) {
        use RunsFrames;

        public bool $visible = true;

        public function __construct(private FakeExecutor $fake, private array $size, private float $scale)
        {
            $this->bootFrames($fake);
        }

        public function executor(): Executor
        {
            return $this->fake;
        }

        protected function frameSize(): array
        {
            return $this->size;
        }

        protected function frameScale(): float
        {
            return $this->scale;
        }

        protected function frameVisible(): bool
        {
            return $this->visible;
        }
    };
}

it('runs a frame for any class that answers the four questions', function () {
    $executor = new FakeExecutor();
    $seen = null;
    $target = frameTarget($executor)->onDraw(function (Drawing2D $g, Frame $frame) use (&$seen) {
        $seen = $frame;
    });

    expect($target->renderFrame())->toBeTrue()
        ->and($seen->width)->toBe(100)
        ->and($seen->height)->toBe(50)
        ->and($seen->scale)->toBe(2.0)
        ->and($executor->frames_begun)->toBe(1)
        ->and($executor->frames_ended)->toBe(1);
});

it('asks the class whether it is visible', function () {
    $target = frameTarget(new FakeExecutor())->onDraw(fn () => null);
    $target->visible = false;

    expect($target->renderFrame())->toBeFalse();
});

it('ends the frame when the hook throws', function () {
    $executor = new FakeExecutor();
    $target = frameTarget($executor)->onDraw(fn () => throw new RuntimeException('sketch bug'));

    expect(fn () => $target->renderFrame())->toThrow(RuntimeException::class, 'sketch bug')
        ->and($executor->frames_ended)->toBe(1);
});

it('drawing() is the Painter over the executor', function () {
    expect(frameTarget(new FakeExecutor())->drawing())->toBeInstanceOf(Painter::class);
});
