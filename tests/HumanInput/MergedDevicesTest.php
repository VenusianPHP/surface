<?php

declare(strict_types=1);

use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\HumanInput\MouseButton;
use Surface\HumanInput\Devices\Keyboard;
use Surface\HumanInput\Devices\MergedKeyboard;
use Surface\HumanInput\Devices\MergedMouse;
use Surface\HumanInput\Devices\Mouse;

it('reads a key down, pressed or released when any keyboard has it so', function (): void {
    $frame = inputFrame();
    $one = (new Keyboard($frame))->update(Key::W, true)->update(Key::A, true)->appendText('w')->setModifiers(new Modifiers(shift: true));
    $two = (new Keyboard($frame))->update(Key::A, true)->appendText('a')->setModifiers(new Modifiers(ctrl: true));
    $merged = new MergedKeyboard($frame, fn (): array => [$one, $two]);

    expect($merged->isDown(Key::W))->toBeTrue()
        ->and($merged->isPressed(Key::A))->toBeTrue()
        ->and($merged->downKeys())->toBe([Key::W, Key::A])
        ->and($merged->pressedKeys())->toBe([Key::W, Key::A])
        ->and($merged->text())->toBe('wa')
        ->and($merged->modifiers())->toEqual(new Modifiers(shift: true, ctrl: true));

    $one->settle();
    $two->settle();
    $two->update(Key::A, false);

    expect($merged->isDown(Key::A))->toBeTrue()
        ->and($merged->wasReleased(Key::A))->toBeTrue()
        ->and($merged->releasedKeys())->toBe([Key::A])
        ->and($merged->isHolding(Key::W, 0))->toBeTrue()
        ->and($merged->isHolding(Key::Q, 0))->toBeFalse();
});

it('reads nothing with no keyboards', function (): void {
    $merged = new MergedKeyboard(inputFrame(), fn (): array => []);

    expect($merged->isDown(Key::A))->toBeFalse()
        ->and($merged->downKeys())->toBe([])
        ->and($merged->text())->toBe('')
        ->and($merged->modifiers())->toEqual(new Modifiers());
});

it('follows the mouse that reported last, and sums motion and wheel', function (): void {
    $frame = inputFrame();
    $one = (new Mouse($frame))->setPosition(1.0, 2.0, 'left')->addMotion(1.0, 0.0)->addWheel(0.0, 1.0);
    $two = (new Mouse($frame))->setPosition(30.0, 40.0, 'right')->addMotion(2.0, 3.0);
    $merged = new MergedMouse($frame, fn (): array => [$one, $two]);

    expect([$merged->x(), $merged->y(), $merged->window()])->toBe([30.0, 40.0, 'right'])
        ->and($merged->motion())->toBe(['dx' => 3.0, 'dy' => 3.0])
        ->and($merged->wheel())->toBe(['dx' => 0.0, 'dy' => 1.0]);

    $one->update(MouseButton::LEFT, true);

    expect($merged->window())->toBe('left')
        ->and($merged->isDown(MouseButton::LEFT))->toBeTrue()
        ->and($merged->isPressed(MouseButton::LEFT))->toBeTrue()
        ->and($merged->button(MouseButton::LEFT)->isDown())->toBeTrue()
        ->and($merged->wasReleased(MouseButton::LEFT))->toBeFalse();
});

it('rests at the origin over no window with no mice', function (): void {
    $merged = new MergedMouse(inputFrame(), fn (): array => []);

    expect([$merged->x(), $merged->y(), $merged->window()])->toBe([0.0, 0.0, null])
        ->and($merged->motion())->toBe(['dx' => 0.0, 'dy' => 0.0])
        ->and($merged->wheel())->toBe(['dx' => 0.0, 'dy' => 0.0])
        ->and($merged->isDown(MouseButton::LEFT))->toBeFalse();
});

it('catches up before the first read of a frame', function (): void {
    $frame = inputFrame();
    $keyboard = new Keyboard($frame);
    $mouse = new Mouse($frame);
    $frame->catchUpWith(function () use ($keyboard, $mouse): void {
        $keyboard->update(Key::Q, true);
        $mouse->setPosition(5.0, 6.0, 'late');
    });
    $keys = new MergedKeyboard($frame, fn (): array => [$keyboard]);
    $pointer = new MergedMouse($frame, fn (): array => [$mouse]);

    expect($keys->isPressed(Key::Q))->toBeTrue()
        ->and($pointer->window())->toBe('late');
});
