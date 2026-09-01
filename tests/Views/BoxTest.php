<?php

use Surface\Contracts\NativeWindows\Views\ContainerHandle;
use Surface\Contracts\NativeWindows\Views\LabelHandle;
use Surface\Contracts\NativeWindows\Views\ViewException;
use Surface\NativeWindows\Enums\ViewType;
use Surface\NativeWindows\Views\Frame;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;

function rootBox(?CallLog $log = null): FakeBox
{
    return new FakeBox($log ?? new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480));
}

it('conjures a label and registers it under its nickname', function () {
    $root = rootBox();

    $title = $root->label('title', 'Hello', 20, 20, 320, 32);

    expect($title)->toBeInstanceOf(LabelHandle::class)
        ->and($title->type())->toBe(ViewType::LABEL)
        ->and($title->nickname())->toBe('title')
        ->and($title->parent())->toBe($root)
        ->and($title->path())->toBe('title')
        ->and($title->frame())->toEqual(new Frame(20, 20, 320, 32))
        ->and($root->has('title'))->toBeTrue()
        ->and($root->child('title'))->toBe($title)
        ->and($root->children())->toBe(['title' => $title]);
});

it('creates, attaches, then frames a new child in that order', function () {
    $log = new CallLog;
    $root = rootBox($log);

    $title = $root->label('title', 'Hello', 20, 20, 320, 32);

    expect($log->ops())->toBe(['createLabel', 'attach', 'setFrame'])
        ->and($log->entries[0]['args'])->toBe(['Hello'])
        ->and($log->entries[1])->toBe(['op' => 'attach', 'pointer' => 1, 'args' => [$title->pointer()]])
        ->and($log->entries[2])->toBe(['op' => 'setFrame', 'pointer' => $title->pointer(), 'args' => [20, 20, 320, 32]]);
});

it('conjures nested boxes whose children carry dotted paths', function () {
    $root = rootBox();

    $side = $root->box('side', 0, 0, 200, 480);
    $title = $side->label('title', 'Hi', 10, 10, 100, 20);

    expect($side)->toBeInstanceOf(ContainerHandle::class)
        ->and($side->type())->toBe(ViewType::BOX)
        ->and($side->path())->toBe('side')
        ->and($title->path())->toBe('side.title')
        ->and($root->path())->toBe('');
});

it('finds handles by dotted path relative to itself', function () {
    $root = rootBox();
    $side = $root->box('side', 0, 0, 200, 480);
    $title = $side->label('title', 'Hi', 10, 10, 100, 20);

    expect($root->find('side.title'))->toBe($title)
        ->and($root->find('side'))->toBe($side)
        ->and($side->find('title'))->toBe($title)
        ->and($root->find(''))->toBe($root)
        ->and($root->find('nope'))->toBeNull()
        ->and($root->find('side.nope'))->toBeNull()
        ->and($root->find('side.title.deeper'))->toBeNull();
});

it('attaches children to its own pointer by default', function () {
    $root = rootBox();

    expect($root->attachPointer())->toBe($root->pointer());
});

it('refuses a nickname already used by a sibling', function () {
    $root = rootBox();
    $root->label('title', 'Hello', 0, 0, 10, 10);

    $root->label('title', 'Again', 0, 0, 10, 10);
})->throws(ViewException::class, "title");

it('refuses nicknames containing dots', function () {
    rootBox()->label('a.b', 'Hello', 0, 0, 10, 10);
})->throws(ViewException::class, 'a.b');

it('refuses empty nicknames', function () {
    rootBox()->box('', 0, 0, 10, 10);
})->throws(ViewException::class);

it('allows the same nickname under different parents', function () {
    $root = rootBox();
    $left = $root->box('left', 0, 0, 100, 100);
    $right = $root->box('right', 100, 0, 100, 100);

    $a = $left->label('title', 'L', 0, 0, 10, 10);
    $b = $right->label('title', 'R', 0, 0, 10, 10);

    expect($root->find('left.title'))->toBe($a)
        ->and($root->find('right.title'))->toBe($b);
});
