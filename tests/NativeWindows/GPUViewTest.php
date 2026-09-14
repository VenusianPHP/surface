<?php

use Surface\Contracts\Drawing\Drawing2D;
use Surface\Contracts\Drawing\Frame;
use Surface\Contracts\Drawing\GPUEngine;
use Surface\Contracts\NativeWindows\GPUViewException;
use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Contracts\NativeWindows\WindowableException;
use Venusian\Surface\Tests\Support\Fakes\FakeGPUView;
use Venusian\Surface\Tests\Support\Fakes\FakeLinuxWindow;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('conjures a GPU region, resolves the engine, and places it', function () {
    $window = new FakeWindow('main');
    $window->backing_scale = 2.0;

    $view = $window->gpu('scene', 'metal', 10, 20, 640, 480);

    expect($view)->toBeInstanceOf(FakeGPUView::class)
        ->and($view->engine())->toBe(GPUEngine::METAL)
        ->and($view->scale())->toBe(2.0)
        ->and($view->applied_frames)->toBe([[10, 20, 640, 480]])
        ->and($view->drawableSize())->toBe([1280, 960])
        ->and($window->gpu_driver->hosts)->toHaveCount(1)
        ->and($window->view('scene'))->toBe($view);
});

it('accepts the enum and null for the default engine', function () {
    $window = new FakeWindow('main');

    expect($window->gpu('a', GPUEngine::METAL, 0, 0, 10, 10)->engine())->toBe(GPUEngine::METAL)
        ->and($window->gpu('b', null, 0, 0, 10, 10)->engine())->toBe(GPUEngine::METAL);
});

it('guards the name like every other conjure', function () {
    $window = new FakeWindow('main');
    $window->gpu('scene', 'metal', 0, 0, 10, 10);

    expect(fn () => $window->gpu('scene', 'metal', 0, 0, 10, 10))->toThrow(WindowableException::class);
});

it('settles into a group with group-relative placement and cascades', function () {
    $window = new FakeWindow('main');
    $group = $window->group('panel', 100, 100, 400, 300);

    $view = $group->gpu('scene', 'metal', 10, 10, 100, 100);

    expect($view->hostedBy())->toBe($group)
        ->and($group->children())->toBe([$view])
        ->and($view->frame())->toBe(['x' => 10, 'y' => 10, 'width' => 100, 'height' => 100]);
});

it('renderFrame skips when there is no hook', function () {
    $window = new FakeWindow('main');
    $view = $window->gpu('scene', 'metal', 0, 0, 10, 10);

    expect($view->renderFrame())->toBeFalse()
        ->and($view->executor()->frames_begun)->toBe(0);
});

it('renderFrame skips when hidden', function () {
    $window = new FakeWindow('main');
    $view = $window->gpu('scene', 'metal', 0, 0, 10, 10)->onDraw(fn () => null);
    $view->hide();

    expect($view->renderFrame())->toBeFalse();
});

it('renderFrame skips when not continuous and nothing is pending; redraw fires once', function () {
    $window = new FakeWindow('main');
    $view = $window->gpu('scene', 'metal', 0, 0, 10, 10)->onDraw(fn () => null)->setContinuous(false);

    expect($view->renderFrame())->toBeFalse();
    $view->redraw();
    expect($view->renderFrame())->toBeTrue()
        ->and($view->renderFrame())->toBeFalse();
});

it('a failed beginFrame does not consume a pending redraw', function () {
    $window = new FakeWindow('main');
    $view = $window->gpu('scene', 'metal', 0, 0, 10, 10)->onDraw(fn () => null)->setContinuous(false);
    $view->redraw();
    $view->executor()->begin_result = false;

    expect($view->renderFrame())->toBeFalse();
    $view->executor()->begin_result = true;
    expect($view->renderFrame())->toBeTrue();
});

it('ends the frame if painter begin fails after beginFrame', function () {
    $window = new FakeWindow('main');
    $view = $window->gpu('scene', 'metal', 0, 0, 10, 10)->onDraw(fn () => null);
    $view->executor()->throw_on_drawable = true;

    expect(fn () => $view->renderFrame())->toThrow(RuntimeException::class)
        ->and($view->executor()->frames_ended)->toBe(1);
});

it('renderFrame skips when the executor has no drawable', function () {
    $window = new FakeWindow('main');
    $view = $window->gpu('scene', 'metal', 0, 0, 10, 10)->onDraw(fn () => null);
    $view->executor()->begin_result = false;

    expect($view->renderFrame())->toBeFalse()
        ->and($view->executor()->frames_ended)->toBe(0);
});

it('the hook receives the Painter and a Frame whose index climbs; the clear colour reaches the executor', function () {
    $window = new FakeWindow('main');
    $seen = [];
    $view = $window->gpu('scene', 'metal', 0, 0, 200, 100)
        ->setClearColor(Color::hex('#101418'))
        ->onDraw(function (Drawing2D $g, Frame $frame) use (&$seen) {
            $seen[] = $frame;
            $g->fillRect(0.0, 0.0, 10.0, 10.0, Color::hex('#fff'));
        });

    $view->renderFrame();
    $view->renderFrame();

    $executor = $view->executor();
    expect($seen)->toHaveCount(2)
        ->and($seen[0]->index)->toBe(0)
        ->and($seen[1]->index)->toBe(1)
        ->and($seen[0]->width)->toBe(200)
        ->and($seen[1]->delta)->toBeGreaterThanOrEqual(0.0)
        ->and($executor->clears[0]->red)->toBe(Color::hex('#101418')->red)
        ->and($executor->draws)->toHaveCount(2)
        ->and($executor->frames_ended)->toBe(2);
});

it('a hook exception propagates but the frame is ended first', function () {
    $window = new FakeWindow('main');
    $view = $window->gpu('scene', 'metal', 0, 0, 10, 10)
        ->onDraw(function (Drawing2D $g) {
            $g->fillRect(0.0, 0.0, 1.0, 1.0, Color::hex('#fff'));
            throw new RuntimeException('sketch bug');
        });

    expect(fn () => $view->renderFrame())->toThrow(RuntimeException::class, 'sketch bug')
        ->and($view->executor()->frames_ended)->toBe(1)
        ->and($view->executor()->draws)->toHaveCount(0);
});

it('remove releases the executor, destroys the native, frees the name', function () {
    $window = new FakeWindow('main');
    $view = $window->gpu('scene', 'metal', 0, 0, 10, 10);

    $view->remove();

    expect($view->executor()->released)->toBeTrue()
        ->and($view->destroyed)->toBeTrue()
        ->and($window->view('scene'))->toBeNull()
        ->and(array_search('release', $view->executor()->calls))->toBeLessThan(count($view->executor()->calls));
});

it('a relayout after a scale change resizes the drawable in pixels', function () {
    $window = new FakeWindow('main');
    $view = $window->gpu('scene', 'metal', 0, 0, 100, 50);

    $view->rescale(2.0);

    expect($view->executor()->size)->toBe([200, 100]);
});

it('renderFrames renders every GPU view, or queues a self-driving one', function () {
    $window = new FakeWindow('main');
    $window->label('title', 'x', 0, 0, 10, 10);
    $a = $window->gpu('a', 'metal', 0, 0, 10, 10)->onDraw(fn () => null);
    $b = $window->gpu('b', 'metal', 0, 0, 10, 10)->onDraw(fn () => null);
    $b->self_driving = true;

    $window->renderFrames();

    expect($a->executor()->frames_begun)->toBe(1)
        ->and($b->executor()->frames_begun)->toBe(0)
        ->and($b->native_queues)->toBe(1);
});

it('LiveApplication::tick lays out, then renders', function () {
    [$app, $dock] = liveApp();
    $app->provisionWindow('main', 400, 600);
    /** @var FakeWindow $main */
    $main = $app->getWindowService()->get('main');
    $view = $main->gpu('scene', 'metal', 0, 0, 100, 100)->onDraw(fn () => null);
    $app->tick(16);
    $main->content_size = [800, 300];

    $app->tick(16);

    $calls = $view->executor()->calls;
    expect($view->executor()->frames_begun)->toBe(2)
        ->and(array_search('resize', $calls))->toBeLessThan(array_search('beginFrame', $calls));
});

it('a window engine that cannot host the engine refuses with GPUViewException', function () {
    $window = new FakeLinuxWindow('main');

    expect(fn () => $window->gpu('scene', 'metal', 0, 0, 10, 10))->toThrow(GPUViewException::class);
});
