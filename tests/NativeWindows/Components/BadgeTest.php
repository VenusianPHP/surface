<?php

use Surface\Contracts\NativeWindows\Views\Color;
use Surface\NativeWindows\Components\Badge;
use Surface\NativeWindows\Components\MessageSeverity;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeLabel;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('mounts a label at the component name path inside the padded box', function () {
    $window = new FakeWindow('main');

    $badge = new Badge($window, 'tag', 10, 20, 100, 32, text: 'New');

    expect($window->view('tag'))->toBeInstanceOf(FakeGroup::class)
        ->and($window->view('tag.text'))->toBeInstanceOf(FakeLabel::class)
        ->and($badge->part('text')->text())->toBe('New')
        ->and($badge->part('text')->frame())->toBe(['x' => 6, 'y' => 6, 'width' => 88, 'height' => 20])
        ->and($badge->frame())->toBe(['x' => 10, 'y' => 20, 'width' => 100, 'height' => 32]);
});

it('paints muted fill and ink when no severity is given', function () {
    $window = new FakeWindow('main');

    new Badge($window, 'tag', 0, 0, 80, 24, text: 'Hi');

    /** @var FakeGroup $root */
    $root = $window->view('tag');
    /** @var FakeLabel $text */
    $text = $window->view('tag.text');

    expect($root->applied_backgrounds)->toHaveCount(1)
        ->and($root->applied_backgrounds[0]->toCss())->toBe(Color::hex('#e5e7eb')->toCss())
        ->and($text->applied_text_colors[0]->toCss())->toBe(Color::hex('#374151')->toCss());
});

it('paints the severity fill on the root and its ink on the text', function () {
    $window = new FakeWindow('main');

    new Badge($window, 'tag', 0, 0, 80, 24, text: 'Ok', severity: MessageSeverity::SUCCESS);

    /** @var FakeGroup $root */
    $root = $window->view('tag');
    /** @var FakeLabel $text */
    $text = $window->view('tag.text');

    expect($root->applied_backgrounds[0]->toCss())->toBe(MessageSeverity::SUCCESS->fill()->toCss())
        ->and($text->applied_text_colors[0]->toCss())->toBe(MessageSeverity::SUCCESS->ink()->toCss());
});

it('setSeverity repaints, including back to the muted defaults', function () {
    $window = new FakeWindow('main');
    $badge = new Badge($window, 'tag', 0, 0, 80, 24, text: 'Hi');

    $badge->setSeverity(MessageSeverity::ERROR);

    /** @var FakeGroup $root */
    $root = $window->view('tag');
    expect($badge->severity())->toBe(MessageSeverity::ERROR)
        ->and(end($root->applied_backgrounds)->toCss())->toBe(MessageSeverity::ERROR->fill()->toCss());

    $badge->setSeverity(null);

    expect($badge->severity())->toBeNull()
        ->and(end($root->applied_backgrounds)->toCss())->toBe(Color::hex('#e5e7eb')->toCss());
});

it('setText writes through to the label', function () {
    $window = new FakeWindow('main');
    $badge = new Badge($window, 'tag', 0, 0, 80, 24, text: 'One');

    $badge->setText('Two');

    expect($badge->text())->toBe('Two')
        ->and($badge->part('text')->text())->toBe('Two');
});

it('place stretches the label inside the padded box and does not hug the root', function () {
    $window = new FakeWindow('main');
    $badge = new Badge($window, 'tag', 0, 0, 100, 32, text: 'New');

    $badge->place(4, 8, 160, 40);

    expect($badge->frame())->toBe(['x' => 4, 'y' => 8, 'width' => 160, 'height' => 40])
        ->and($badge->part('text')->frame())->toBe(['x' => 6, 'y' => 6, 'width' => 148, 'height' => 28]);
});

it('removal frees the root and part names', function () {
    $window = new FakeWindow('main');
    $badge = new Badge($window, 'tag', 0, 0, 80, 24, text: 'Hi');

    $badge->remove();

    expect($window->view('tag'))->toBeNull()
        ->and($window->view('tag.text'))->toBeNull()
        ->and($badge->part('text'))->toBeNull();
});
