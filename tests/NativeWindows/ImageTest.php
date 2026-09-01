<?php

use Surface\Contracts\NativeWindows\WindowableException;
use Venusian\Surface\Tests\Support\Fakes\FakeImage;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

/*
|--------------------------------------------------------------------------
| Conjuring an image
|--------------------------------------------------------------------------
|
| A picture loaded from a file path — the one loading story both engines
| share. Surface holds the path it believes in; the engine loads and
| scales through applyPath(). Bytes-in-memory are the sketch's business:
| write a temp file, hand the path over.
|
*/

it('conjures an image with a path, registers it by name and places it at once', function () {
    $window = new FakeWindow('main');

    $image = $window->image('pic', '/tmp/apod.jpg', 10, 20, 300, 200);

    expect($image)->toBeInstanceOf(FakeImage::class)
        ->and($image->name())->toBe('pic')
        ->and($image->path())->toBe('/tmp/apod.jpg')
        ->and($window->view('pic'))->toBe($image)
        ->and($image->frame())->toBe(['x' => 10, 'y' => 20, 'width' => 300, 'height' => 200])
        ->and($image->applied_frames)->toBe([[10, 20, 300, 200]]);
});

it('conjures an empty image when no path is known yet', function () {
    $window = new FakeWindow('main');

    $image = $window->image('pic', null, 0, 0, 300, 200);

    expect($image->path())->toBeNull()
        ->and($image->applied_paths)->toBe([]);
});

it('refuses a second view under a taken image name', function () {
    $window = new FakeWindow('main');
    $window->image('pic', null, 0, 0, 1, 1);

    expect(fn () => $window->image('pic', null, 0, 0, 1, 1))
        ->toThrow(WindowableException::class, "View 'pic' already exists");
});

it('setPath records the new truth and the engine sees it', function () {
    $window = new FakeWindow('main');
    $image = $window->image('pic', null, 0, 0, 300, 200);

    $image->setPath('/tmp/apod.jpg');

    expect($image->path())->toBe('/tmp/apod.jpg')
        ->and($image->applied_paths)->toBe(['/tmp/apod.jpg']);
});

it('setPath re-measures a hugging image, because the content just changed', function () {
    $window = new FakeWindow('main');
    $image = $window->image('pic', null, 10, 20, 1, 1);
    $image->natural_size = [480, 320];
    $image->hug();

    $image->setPath('/tmp/apod.jpg');

    expect($image->frame())->toBe(['x' => 10, 'y' => 20, 'width' => 480, 'height' => 320]);
});

it('removal destroys the native node and frees the name', function () {
    $window = new FakeWindow('main');
    $image = $window->image('pic', '/tmp/apod.jpg', 0, 0, 1, 1);

    $image->remove();

    expect($image->destroyed)->toBeTrue()
        ->and($window->view('pic'))->toBeNull();
});
