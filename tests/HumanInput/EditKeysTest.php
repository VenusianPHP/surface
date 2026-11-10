<?php

use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\Windows\Input\EditCommand;
use Surface\HumanInput\Keymaps\EditKeys;

it('uses Command for chords and Option for words on macOS', function () {
    expect(EditKeys::command(Key::V, new Modifiers(meta: true), true))->toBe(EditCommand::Paste)
        ->and(EditKeys::command(Key::V, new Modifiers(ctrl: true), true))->toBeNull()
        ->and(EditKeys::command(Key::LEFT, new Modifiers(alt: true), true))->toBe(EditCommand::MoveWordLeft)
        ->and(EditKeys::command(Key::LEFT, new Modifiers(meta: true), true))->toBe(EditCommand::MoveToLineStart)
        ->and(EditKeys::command(Key::Z, new Modifiers(shift: true, meta: true), true))->toBe(EditCommand::Redo);
});

it('uses Control for chords and words elsewhere', function () {
    expect(EditKeys::command(Key::V, new Modifiers(ctrl: true), false))->toBe(EditCommand::Paste)
        ->and(EditKeys::command(Key::V, new Modifiers(meta: true), false))->toBeNull()
        ->and(EditKeys::command(Key::LEFT, new Modifiers(ctrl: true), false))->toBe(EditCommand::MoveWordLeft)
        ->and(EditKeys::command(Key::HOME, new Modifiers(ctrl: true), false))->toBe(EditCommand::MoveToDocumentStart);
});

it('selects with Shift and binds plain editing keys', function () {
    expect(EditKeys::command(Key::RIGHT, new Modifiers(shift: true), false))->toBe(EditCommand::SelectRight)
        ->and(EditKeys::command(Key::BACKSPACE, new Modifiers(), false))->toBe(EditCommand::DeleteBackward)
        ->and(EditKeys::command(Key::TAB, new Modifiers(shift: true), false))->toBe(EditCommand::InsertBacktab)
        ->and(EditKeys::command(Key::A, new Modifiers(), false))->toBeNull()
        ->and(EditKeys::command(Key::A, new Modifiers(shift: true), false))->toBeNull();
});
