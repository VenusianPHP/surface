<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Windows\Primitives\ImagePosition;
use Surface\Contracts\Windows\WindowException;
use Surface\Framebuffers\Native\NativeFullFramebuffer;
use Venusian\Surface\Tests\Fixtures\FakeHost;

/*
 * Button images and the bordered look, on push and toggle buttons alike.
 */

dataset('buttons', [
    'push' => ['button'],
    'toggle' => ['toggle'],
]);

function imageButton(string $kind): object
{
    $column = (new FakeHost('main'))->column('m');

    return $kind === 'button' ? $column->button('b', 'Open') : $column->toggleButton('b', 'Open');
}

it('stores the image, its position and its scale, and applies them once', function (string $kind): void {
    $button = imageButton($kind);
    $image = new NativeFullFramebuffer(FormatSpec::rgba8(), 32, 32);

    $button->setImage($image, ImagePosition::ABOVE, 2.0);

    expect($button->image())->toBe($image)
        ->and($button->imagePosition())->toBe(ImagePosition::ABOVE)
        ->and($button->imageScale())->toBe(2.0)
        ->and(array_values(array_filter($button->log, fn (string $line): bool => str_starts_with($line, 'image:'))))->toBe(['image:32x32,above,2']);
})->with('buttons');

it('starts with no image, leading, scale 1, and bordered', function (string $kind): void {
    $button = imageButton($kind);

    expect($button->image())->toBeNull()
        ->and($button->imagePosition())->toBe(ImagePosition::LEADING)
        ->and($button->imageScale())->toBe(1.0)
        ->and($button->isBordered())->toBeTrue();
})->with('buttons');

it('refuses a scale that is not positive', function (string $kind): void {
    $button = imageButton($kind);

    expect(fn () => $button->setImage(new NativeFullFramebuffer(FormatSpec::rgba8(), 1, 1), scale: 0))
        ->toThrow(WindowException::class, 'Image scale must be positive.');
})->with('buttons');

it('removes the image with null', function (string $kind): void {
    $button = imageButton($kind);
    $button->setImage(new NativeFullFramebuffer(FormatSpec::rgba8(), 4, 4));

    $button->setImage(null);

    expect($button->image())->toBeNull()
        ->and($button->log)->toContain('image:none,leading,1');
})->with('buttons');

it('applies the bordered look only when it changes', function (string $kind): void {
    $button = imageButton($kind);

    $button->setBordered(false);
    $button->setBordered(false);
    $button->setBordered(true);

    expect($button->isBordered())->toBeTrue()
        ->and(array_values(array_filter($button->log, fn (string $line): bool => str_starts_with($line, 'bordered:'))))->toBe(['bordered:0', 'bordered:1']);
})->with('buttons');
