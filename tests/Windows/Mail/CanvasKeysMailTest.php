<?php

declare(strict_types=1);

use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\Windows\Input\EditCommand;
use Surface\Contracts\Windows\Mail\View\EditCommanded;
use Surface\Contracts\Windows\Mail\View\KeyPressed;
use Surface\Contracts\Windows\Mail\View\KeyReleased;
use Surface\Contracts\Windows\Mail\View\PrimitiveMail;
use Surface\Contracts\Windows\Mail\View\TextCommitted;

it('names the canvas key mail by window and path and carries each payload', function (): void {
    $pressed = new KeyPressed('main', 'm.editor', 'u1', Key::LEFT, '', new Modifiers(shift: true), false);
    $released = new KeyReleased('main', 'm.editor', 'u1', Key::LEFT, new Modifiers(shift: true));
    $text = new TextCommitted('main', 'm.editor', 'u1', 'é');
    $command = new EditCommanded('main', 'm.editor', 'u1', EditCommand::SelectLeft);

    expect($pressed)->toBeInstanceOf(PrimitiveMail::class)
        ->and($pressed->name())->toBe('view.key-pressed.main.m.editor')
        ->and([$pressed->window, $pressed->path, $pressed->uuid])->toBe(['main', 'm.editor', 'u1'])
        ->and($pressed->key)->toBe(Key::LEFT)
        ->and($pressed->character)->toBe('')
        ->and($pressed->modifiers->shift)->toBeTrue()
        ->and($pressed->repeat)->toBeFalse()
        ->and($released)->toBeInstanceOf(PrimitiveMail::class)
        ->and($released->name())->toBe('view.key-released.main.m.editor')
        ->and($released->key)->toBe(Key::LEFT)
        ->and($released->modifiers->shift)->toBeTrue()
        ->and($text)->toBeInstanceOf(PrimitiveMail::class)
        ->and($text->name())->toBe('view.text-committed.main.m.editor')
        ->and($text->text)->toBe('é')
        ->and($command)->toBeInstanceOf(PrimitiveMail::class)
        ->and($command->name())->toBe('view.edit-commanded.main.m.editor')
        ->and($command->command)->toBe(EditCommand::SelectLeft);
});

it('names the forty edit commands by their platform-neutral values', function (): void {
    expect(EditCommand::cases())->toHaveCount(40)
        ->and(EditCommand::from('move-to-document-end'))->toBe(EditCommand::MoveToDocumentEnd)
        ->and(EditCommand::from('select-page-down'))->toBe(EditCommand::SelectPageDown)
        ->and(EditCommand::from('delete-word-backward'))->toBe(EditCommand::DeleteWordBackward)
        ->and(EditCommand::from('insert-backtab'))->toBe(EditCommand::InsertBacktab)
        ->and(EditCommand::from('select-to-line-start'))->toBe(EditCommand::SelectToLineStart)
        ->and(EditCommand::from('yank'))->toBe(EditCommand::Yank);
});
