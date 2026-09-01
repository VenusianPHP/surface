<?php

use Surface\Contracts\NativeWindows\Views\ImageHandle;
use Surface\Contracts\NativeWindows\Views\ViewException;
use Surface\NativeWindows\Enums\ImageFit;
use Surface\NativeWindows\Enums\ViewType;
use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\Size;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;

it('conjures an image from a loadable path', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));

    $image = $root->image('pic', __FILE__, 10, 10, 240, 60);

    expect($image)->toBeInstanceOf(ImageHandle::class)
        ->and($image->type())->toBe(ViewType::IMAGE)
        ->and($image->source())->toBe(__FILE__)
        ->and($image->currentFit())->toBe(ImageFit::CONTAIN)
        ->and($image->path())->toBe('pic')
        ->and($log->ops())->toBe(['createImage', 'attach', 'setFrame', 'setFit']);
});

it('refuses an unloadable path on create without calling native', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));

    expect(fn () => $root->image('pic', '/no/such/wave3.png', 0, 0, 10, 10))
        ->toThrow(ViewException::class, '/no/such/wave3.png')
        ->and($log->ops())->toBe([]);
});

it('refuses when native will not load the file', function () {
    $root = new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480));
    $root->loadImages = false;

    expect(fn () => $root->image('pic', __FILE__, 0, 0, 10, 10))
        ->toThrow(ViewException::class, __FILE__);
});

it('keeps the previous source when a later path will not load', function () {
    $root = new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480));
    $image = $root->image('pic', __FILE__, 0, 0, 10, 10);

    expect(fn () => $image->setSource('/nope.png'))->toThrow(ViewException::class)
        ->and($image->source())->toBe(__FILE__);
});

it('keeps the previous source when native rejects a later path', function () {
    $root = new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480));
    $image = $root->image('pic', __FILE__, 0, 0, 10, 10);
    $image->acceptSource = false;
    $rejected = __DIR__.'/BoxTest.php';

    expect(fn () => $image->setSource($rejected))->toThrow(ViewException::class, $rejected)
        ->and($image->source())->toBe(__FILE__);
});

it('hugs the pixel size', function () {
    $root = new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480));
    $image = $root->image('pic', __FILE__, 10, 10, 240, 60);
    $image->natural = new Size(80, 20);

    $image->hug();

    expect($image->frame())->toEqual(new Frame(10, 10, 80, 20))
        ->and($image->measure())->toEqual(new Size(80, 20));
});

it('switches fit as bookkeeping', function () {
    $root = new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480));
    $image = $root->image('pic', __FILE__, 0, 0, 10, 10);

    $image->fit(ImageFit::COVER);

    expect($image->currentFit())->toBe(ImageFit::COVER);
});
