<?php

use Surface\NativeWindows\Components\Message;
use Surface\NativeWindows\Components\MessageSeverity;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeLabel;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('paints the severity fill on the root and its ink on the text', function () {
    $window = new FakeWindow('main');

    $message = new Message($window, 'note', 0, 0, 300, 40, text: 'Saved.', severity: MessageSeverity::SUCCESS);

    /** @var FakeGroup $root */
    $root = $window->view('note');
    /** @var FakeLabel $text */
    $text = $window->view('note.text');

    expect($root->applied_backgrounds)->toHaveCount(1)
        ->and($root->applied_backgrounds[0]->toCss())->toBe(MessageSeverity::SUCCESS->fill()->toCss())
        ->and($text->applied_text_colors[0]->toCss())->toBe(MessageSeverity::SUCCESS->ink()->toCss())
        ->and($window->view('note.close'))->toBeNull();
});

it('setSeverity repaints both', function () {
    $window = new FakeWindow('main');
    $message = new Message($window, 'note', 0, 0, 300, 40, text: 'Careful.');

    $message->setSeverity(MessageSeverity::ERROR);

    /** @var FakeGroup $root */
    $root = $window->view('note');
    expect($message->severity())->toBe(MessageSeverity::ERROR)
        ->and(end($root->applied_backgrounds)->toCss())->toBe(MessageSeverity::ERROR->fill()->toCss());
});

it('setText writes through to the label', function () {
    $window = new FakeWindow('main');
    $message = new Message($window, 'note', 0, 0, 300, 40, text: 'One.');

    $message->setText('Two.');

    expect($message->part('text')->text())->toBe('Two.');
});

it('a closable message dismisses itself on the close click and then hooks', function () {
    $window = new FakeWindow('main');
    $order = [];
    $message = new Message($window, 'note', 0, 0, 300, 40, text: 'Bye.', closable: true);
    $message->onClose(function () use (&$order, $window) {
        $order[] = is_null($window->view('note')) ? 'gone-first' : 'still-there';
    });

    $close = $message->part('close');
    expect($close)->not->toBeNull()
        ->and($close->frame())->toBe(['x' => 268, 'y' => 9, 'width' => 22, 'height' => 22]);

    $close->click();

    expect($order)->toBe(['gone-first'])
        ->and($window->view('note'))->toBeNull()
        ->and($window->view('note.text'))->toBeNull()
        ->and($window->view('note.close'))->toBeNull();
});

it('the text makes room for the close button only when closable', function () {
    $window = new FakeWindow('main');
    $plain = new Message($window, 'a', 0, 0, 300, 40, text: 'x');
    $closable = new Message($window, 'b', 0, 0, 300, 40, text: 'x', closable: true);

    expect($plain->part('text')->frame()['width'])->toBe(280)
        ->and($closable->part('text')->frame()['width'])->toBe(248);
});
