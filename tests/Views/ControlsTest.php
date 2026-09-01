<?php

use Surface\Contracts\NativeWindows\Views\ContainerHandle;
use Surface\Contracts\NativeWindows\Views\ControlHandle;
use Surface\Contracts\NativeWindows\Views\ViewException;
use Surface\NativeWindows\Enums\ViewType;
use Surface\NativeWindows\Views\Frame;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;

dataset('controls', [
    'button' => [fn (ContainerHandle $b) => $b->button('c', 'Go', 1, 2, 30, 40), ViewType::BUTTON, 'createButton'],
    'entry' => [fn (ContainerHandle $b) => $b->entry('c', '', 1, 2, 30, 40), ViewType::ENTRY, 'createEntry'],
    'password' => [fn (ContainerHandle $b) => $b->password('c', '', 1, 2, 30, 40), ViewType::PASSWORD, 'createPassword'],
    'checkbox' => [fn (ContainerHandle $b) => $b->checkbox('c', 'Agree', 1, 2, 30, 40), ViewType::CHECKBOX, 'createCheckbox'],
    'radio' => [fn (ContainerHandle $b) => $b->radio('c', 'Small', 'size', 1, 2, 30, 40), ViewType::RADIO, 'createRadio'],
    'switch' => [fn (ContainerHandle $b) => $b->switch('c', 1, 2, 30, 40), ViewType::SWITCH, 'createSwitch'],
    'slider' => [fn (ContainerHandle $b) => $b->slider('c', 0.0, 1.0, 0.5, 1, 2, 30, 40), ViewType::SLIDER, 'createSlider'],
    'progress' => [fn (ContainerHandle $b) => $b->progress('c', 1, 2, 30, 40), ViewType::PROGRESS, 'createProgress'],
    'spinner' => [fn (ContainerHandle $b) => $b->spinner('c', 1, 2, 30, 40), ViewType::SPINNER, 'createSpinner'],
    'dropdown' => [fn (ContainerHandle $b) => $b->dropdown('c', ['A'], 1, 2, 30, 40), ViewType::DROPDOWN, 'createDropdown'],
]);

it('conjures every control into the tree the same way', function (Closure $conjure, ViewType $type, string $createOp) {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $side = $root->box('side', 0, 0, 200, 200);
    $log->clear();

    $control = $conjure($side);

    expect($control)->toBeInstanceOf(ControlHandle::class)
        ->and($control->type())->toBe($type)
        ->and($control->path())->toBe('side.c')
        ->and($control->frame())->toEqual(new Frame(1, 2, 30, 40))
        ->and($control->isEnabled())->toBeTrue()
        ->and($root->find('side.c'))->toBe($control)
        ->and($log->ops())->toBe([$createOp, 'attach', 'setFrame']);
})->with('controls');

it('removes every control terminally and refuses further mutation', function (Closure $conjure) {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $control = $conjure($root);
    $log->clear();

    $control->remove();

    expect($log->ops())->toBe(['detach'])
        ->and($control->isAlive())->toBeFalse()
        ->and($root->has('c'))->toBeFalse()
        ->and(fn () => $control->enabled(false))->toThrow(ViewException::class);
})->with('controls');

it('refuses a second control under the same nickname', function (Closure $conjure) {
    $root = new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480));
    $conjure($root);

    $conjure($root);
})->with('controls')->throws(ViewException::class, "'c'");

it('measures, hugs and centers like any view', function (Closure $conjure) {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $control = $conjure($root);
    $control->natural = new \Surface\NativeWindows\Views\Size(50, 20);
    $log->clear();

    $control->hug()->center();

    expect($control->frame())->toEqual(new Frame(295, 230, 50, 20))
        ->and($log->ops())->toBe(['measure', 'setFrame', 'setFrame']);
})->with('controls');
