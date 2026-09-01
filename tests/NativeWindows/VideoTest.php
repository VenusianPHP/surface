<?php

use Surface\Contracts\NativeWindows\WindowableException;
use Venusian\Surface\Tests\Support\Fakes\FakeVideo;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

/*
|--------------------------------------------------------------------------
| Conjuring a video
|--------------------------------------------------------------------------
|
| Moving pictures from a file path — the one loading story both engines
| share (bytes from callHttp go through a temp file, like Image). Surface
| holds the path, playing and muted flags it believes in; the engine
| translates through applyPath / applyPlaying / applyMuted.
|
*/

it('conjures a video with a path, registers it by name and places it at once', function () {
    $window = new FakeWindow('main');

    $video = $window->video('clip', '/tmp/apod.mp4', 10, 20, 320, 240);

    expect($video)->toBeInstanceOf(FakeVideo::class)
        ->and($video->name())->toBe('clip')
        ->and($video->path())->toBe('/tmp/apod.mp4')
        ->and($window->view('clip'))->toBe($video)
        ->and($video->frame())->toBe(['x' => 10, 'y' => 20, 'width' => 320, 'height' => 240])
        ->and($video->applied_frames)->toBe([[10, 20, 320, 240]]);
});

it('conjures an empty video when no path is known yet', function () {
    $window = new FakeWindow('main');

    $video = $window->video('clip', null, 0, 0, 320, 240);

    expect($video->path())->toBeNull()
        ->and($video->applied_paths)->toBe([]);
});

it('refuses a second view under a taken video name', function () {
    $window = new FakeWindow('main');
    $window->video('clip', null, 0, 0, 1, 1);

    expect(fn () => $window->video('clip', null, 0, 0, 1, 1))
        ->toThrow(WindowableException::class, "View 'clip' already exists");
});

it('setPath records the new truth and the engine sees it', function () {
    $window = new FakeWindow('main');
    $video = $window->video('clip', null, 0, 0, 320, 240);

    $video->setPath('/tmp/apod.mp4');

    expect($video->path())->toBe('/tmp/apod.mp4')
        ->and($video->applied_paths)->toBe(['/tmp/apod.mp4']);
});

it('is not playing when conjured and play/pause cross to the engine in order', function () {
    $window = new FakeWindow('main');
    $video = $window->video('clip', '/tmp/apod.mp4', 0, 0, 320, 240);

    expect($video->isPlaying())->toBeFalse();

    $video->play()->pause();

    expect($video->isPlaying())->toBeFalse()
        ->and($video->applied_playings)->toBe([true, false]);
});

it('mute crosses to the engine and is remembered', function () {
    $window = new FakeWindow('main');
    $video = $window->video('clip', '/tmp/apod.mp4', 0, 0, 320, 240);

    $video->setMuted(true);

    expect($video->isMuted())->toBeTrue()
        ->and($video->applied_muteds)->toBe([true]);
});

it('removal destroys the native node and frees the name', function () {
    $window = new FakeWindow('main');
    $video = $window->video('clip', '/tmp/apod.mp4', 0, 0, 1, 1);

    $video->remove();

    expect($video->destroyed)->toBeTrue()
        ->and($window->view('clip'))->toBeNull();
});
