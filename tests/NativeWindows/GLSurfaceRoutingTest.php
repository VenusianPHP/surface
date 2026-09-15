<?php

use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Drawing\GPUEngine;
use Surface\Contracts\Drawing\GPUHost;
use Surface\Contracts\Drawing\SurfaceKind;
use Surface\Contracts\NativeWindows\GPUViewException;
use Venusian\Surface\Tests\Support\Fakes\FakeGLSurface;
use Venusian\Surface\Tests\Support\Fakes\FakeGPUEngineDriver;
use Venusian\Surface\Tests\Support\Fakes\FakeGPUView;
use Venusian\Surface\Tests\Support\Fakes\FakeLinuxWindow;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('SurfaceKind is an int enum with the two slice-2 kinds', function () {
    expect(SurfaceKind::LAYER->value)->toBe(0)
        ->and(SurfaceKind::GL_CONTEXT->value)->toBe(1);
});

it('GPUHost carries no GL surface for a layer attach', function () {
    $host = new GPUHost(0, 10, 10, 1.0);

    expect($host->gl)->toBeNull();
});

it('a layer engine gets a null gl on the host and a plain fake view', function () {
    $window = new FakeWindow('main');
    $window->gpu_driver = new FakeGPUEngineDriver(GPUEngine::METAL, null, SurfaceKind::LAYER);

    $view = $window->gpu('scene', 'metal', 0, 0, 10, 10);

    expect($view)->toBeInstanceOf(FakeGPUView::class)
        ->and($window->gpu_driver->hosts[0]->gl)->toBeNull()
        ->and($view->gl)->toBeNull();
});

it('a GL engine gets a FakeGLSurface on the host, minted before attach()', function () {
    $window = new FakeWindow('main');
    $window->backing_scale = 2.0;
    $window->gpu_driver = new FakeGPUEngineDriver(GPUEngine::OPENGL, null, SurfaceKind::GL_CONTEXT);

    $view = $window->gpu('scene', 'opengl', 0, 0, 100, 50);

    expect($window->gpu_driver->hosts[0]->gl)->toBeInstanceOf(FakeGLSurface::class)
        ->and($view->gl)->toBe($window->gpu_driver->hosts[0]->gl)
        ->and($view->executor()->gl)->toBe($view->gl);
});

it('a GL engine refuses a host with no GL surface', function () {
    $driver = new FakeGPUEngineDriver(GPUEngine::OPENGL, null, SurfaceKind::GL_CONTEXT);

    expect(fn () => $driver->attach(new GPUHost(0, 10, 10, 1.0)))->toThrow(DrawingException::class);
});

it('beginFrame sees the context current before it and endFrame presents after it', function () {
    $window = new FakeWindow('main');
    $window->gpu_driver = new FakeGPUEngineDriver(GPUEngine::OPENGL, null, SurfaceKind::GL_CONTEXT);
    $view = $window->gpu('scene', 'opengl', 0, 0, 10, 10)->onDraw(fn () => null);

    $view->renderFrame();

    $gl = $view->gl;
    expect($gl->made_current)->toBe(1)
        ->and($gl->presented)->toBe(1)
        ->and($gl->log)->toBe(['makeCurrent', 'beginFrame', 'endFrame', 'present']);
});

it('the Linux fake refuses a layer engine and hosts a GL one', function () {
    $window = new FakeLinuxWindow('main');
    $window->gpu_driver = new FakeGPUEngineDriver(GPUEngine::METAL, null, SurfaceKind::LAYER);
    expect(fn () => $window->gpu('a', 'metal', 0, 0, 10, 10))->toThrow(GPUViewException::class);

    $gl_window = new FakeLinuxWindow('other');
    $gl_window->gpu_driver = new FakeGPUEngineDriver(GPUEngine::OPENGL, null, SurfaceKind::GL_CONTEXT);
    expect($gl_window->gpu('b', 'opengl', 0, 0, 10, 10)->gl)->toBeInstanceOf(FakeGLSurface::class);
});
