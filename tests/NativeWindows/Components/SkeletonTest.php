<?php

use Surface\Contracts\NativeWindows\Views\Color;
use Surface\NativeWindows\Components\Skeleton;
use Surface\NativeWindows\Components\SkeletonShape;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('mounts a painted root with no child parts', function () {
    $window = new FakeWindow('main');
    $skeleton = new Skeleton($window, 'bone', 10, 20, 80, 80);

    /** @var FakeGroup $root */
    $root = $window->view('bone');

    expect($root)->toBeInstanceOf(FakeGroup::class)
        ->and($skeleton->frame())->toBe(['x' => 10, 'y' => 20, 'width' => 80, 'height' => 80])
        ->and($skeleton->shape())->toBe(SkeletonShape::RECTANGLE)
        ->and($root->applied_backgrounds)->toHaveCount(1)
        ->and($root->applied_backgrounds[0]->toCss())->toBe(Color::hex('#e5e7eb')->toCss())
        ->and($root->children())->toBe([])
        ->and($skeleton->part('shape'))->toBeNull();
});

it('CIRCLE records intent and is still a square painted group', function () {
    $window = new FakeWindow('main');
    $skeleton = new Skeleton($window, 'avatar', 0, 0, 40, 40, SkeletonShape::CIRCLE);

    /** @var FakeGroup $root */
    $root = $window->view('avatar');

    expect($skeleton->shape())->toBe(SkeletonShape::CIRCLE)
        ->and($skeleton->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 40, 'height' => 40])
        ->and($root->applied_backgrounds[0]->toCss())->toBe(Color::hex('#e5e7eb')->toCss())
        ->and($root->children())->toBe([]);
});

it('removal frees the name', function () {
    $window = new FakeWindow('main');
    $skeleton = new Skeleton($window, 'bone', 0, 0, 80, 80);

    $skeleton->remove();

    expect($window->view('bone'))->toBeNull();
});
