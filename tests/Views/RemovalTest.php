<?php

use Surface\Contracts\NativeWindows\Views\ViewException;
use Surface\NativeWindows\Enums\FontWeight;
use Surface\NativeWindows\Enums\TextAlignment;
use Surface\NativeWindows\Views\Color;
use Surface\NativeWindows\Views\Frame;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;

function treeUnderTest(CallLog $log): FakeBox
{
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $side = $root->box('side', 0, 0, 200, 480);
    $side->label('title', 'Hi', 10, 10, 100, 20);
    $root->label('footer', 'Bye', 0, 460, 640, 20);
    $log->clear();

    return $root;
}

it('detaches natively, forgets the nickname and kills the handle', function () {
    $log = new CallLog;
    $root = treeUnderTest($log);
    $footer = $root->child('footer');

    $footer->remove();

    expect($log->entries)->toBe([['op' => 'detach', 'pointer' => $footer->pointer(), 'args' => []]])
        ->and($root->has('footer'))->toBeFalse()
        ->and($root->find('footer'))->toBeNull()
        ->and($footer->isAlive())->toBeFalse();
});

it('removes a subtree with one native detach and kills every handle in it', function () {
    $log = new CallLog;
    $root = treeUnderTest($log);
    $side = $root->child('side');
    $title = $root->find('side.title');

    $side->remove();

    expect($log->ops())->toBe(['detach'])
        ->and($log->entries[0]['pointer'])->toBe($side->pointer())
        ->and($side->isAlive())->toBeFalse()
        ->and($title->isAlive())->toBeFalse()
        ->and($root->find('side.title'))->toBeNull();
});

it('lets the parent, the window path and the handle converge on the same removal', function () {
    $log = new CallLog;
    $root = treeUnderTest($log);
    $title = $root->find('side.title');

    $root->child('side')->removeChild('title');

    expect($title->isAlive())->toBeFalse()
        ->and($root->find('side.title'))->toBeNull()
        ->and($root->find('side'))->not->toBeNull();
});

it('throws when asked to remove a child that is not there', function () {
    $root = treeUnderTest(new CallLog);

    $root->child('side')->removeChild('ghost');
})->throws(ViewException::class, 'side.ghost');

it('frees the nickname for reuse after removal', function () {
    $root = treeUnderTest(new CallLog);
    $root->removeChild('footer');

    $again = $root->label('footer', 'New', 0, 0, 10, 10);

    expect($root->child('footer'))->toBe($again);
});

it('refuses every mutation on a dead label', function (Closure $mutate) {
    $root = treeUnderTest(new CallLog);
    $title = $root->find('side.title');
    $title->remove();

    $mutate($title);
})->with([
    'text' => fn ($label) => $label->text('x'),
    'textColor' => fn ($label) => $label->textColor(Color::hex('#fff')),
    'font' => fn ($label) => $label->font('', 12.0, FontWeight::BOLD),
    'position' => fn ($label) => $label->position(1, 1),
    'size' => fn ($label) => $label->size(1, 1),
    'bgColor' => fn ($label) => $label->bgColor(Color::hex('#fff')),
    'remove' => fn ($label) => $label->remove(),
    'align' => fn ($label) => $label->align(TextAlignment::CENTER),
    'measure' => fn ($label) => $label->measure(),
    'hug' => fn ($label) => $label->hug(),
    'center' => fn ($label) => $label->center(),
])->throws(ViewException::class, 'side.title');

it('refuses to conjure under a dead box', function (Closure $conjure) {
    $root = treeUnderTest(new CallLog);
    $side = $root->child('side');
    $side->remove();

    $conjure($side);
})->with([
    'label' => fn ($box) => $box->label('x', 'x', 0, 0, 1, 1),
    'box' => fn ($box) => $box->box('x', 0, 0, 1, 1),
])->throws(ViewException::class, 'side');

it('still reports the dead handle\'s last known identity', function () {
    $root = treeUnderTest(new CallLog);
    $title = $root->find('side.title');

    $title->remove();

    expect($title->nickname())->toBe('title')
        ->and($title->path())->toBe('side.title')
        ->and($title->pointer())->toBeInt();
});
