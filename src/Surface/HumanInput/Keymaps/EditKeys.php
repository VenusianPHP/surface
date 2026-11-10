<?php

namespace Surface\HumanInput\Keymaps;

use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\Windows\Input\EditCommand;

/**
 * The platform's standard text-editing keys as edit commands, for a driver whose library has no
 * key bindings of its own. On macOS the chord key is Command (meta), word moves take Option, and
 * Command with an arrow goes to a line or document end; elsewhere Control does both.
 */
final class EditKeys
{
    public static function command(Key $key, Modifiers $mods, bool $macos = PHP_OS_FAMILY === 'Darwin'): ?EditCommand
    {
        $plain = ! $mods->ctrl && ! $mods->alt && ! $mods->meta;
        $ctrl = $mods->ctrl && ! $mods->alt && ! $mods->meta;
        $option = $mods->alt && ! $mods->ctrl && ! $mods->meta;
        $command = $mods->meta && ! $mods->ctrl && ! $mods->alt;
        $chord = $macos ? $command : $ctrl;
        $word = $macos ? $option : $ctrl;
        $lineEnd = $macos && $command;

        $move = match (true) {
            $plain && $key === Key::LEFT => [EditCommand::MoveLeft, EditCommand::SelectLeft],
            $plain && $key === Key::RIGHT => [EditCommand::MoveRight, EditCommand::SelectRight],
            $plain && $key === Key::UP => [EditCommand::MoveUp, EditCommand::SelectUp],
            $plain && $key === Key::DOWN => [EditCommand::MoveDown, EditCommand::SelectDown],
            $word && $key === Key::LEFT => [EditCommand::MoveWordLeft, EditCommand::SelectWordLeft],
            $word && $key === Key::RIGHT => [EditCommand::MoveWordRight, EditCommand::SelectWordRight],
            ($plain && $key === Key::HOME) || ($lineEnd && $key === Key::LEFT) => [EditCommand::MoveToLineStart, EditCommand::SelectToLineStart],
            ($plain && $key === Key::END) || ($lineEnd && $key === Key::RIGHT) => [EditCommand::MoveToLineEnd, EditCommand::SelectToLineEnd],
            $chord && $key === ($macos ? Key::UP : Key::HOME) => [EditCommand::MoveToDocumentStart, EditCommand::SelectToDocumentStart],
            $chord && $key === ($macos ? Key::DOWN : Key::END) => [EditCommand::MoveToDocumentEnd, EditCommand::SelectToDocumentEnd],
            $plain && $key === Key::PAGE_UP => [EditCommand::PageUp, EditCommand::SelectPageUp],
            $plain && $key === Key::PAGE_DOWN => [EditCommand::PageDown, EditCommand::SelectPageDown],
            default => null,
        };
        if (! is_null($move)) {
            return $move[$mods->shift ? 1 : 0];
        }
        // Shift with a key that does not move: only the chord's Shift-Z (Redo) and Shift-Tab are bound.
        if ($mods->shift && ! ($chord && $key === Key::Z) && $key !== Key::TAB) {
            return null;
        }

        return match (true) {
            $plain && $key === Key::BACKSPACE => EditCommand::DeleteBackward,
            $plain && $key === Key::DELETE => EditCommand::DeleteForward,
            $word && $key === Key::BACKSPACE => EditCommand::DeleteWordBackward,
            $word && $key === Key::DELETE => EditCommand::DeleteWordForward,
            $macos && $ctrl && $key === Key::K => EditCommand::DeleteToLineEnd,
            $macos && $ctrl && $key === Key::Y => EditCommand::Yank,
            $chord && $key === Key::A => EditCommand::SelectAll,
            $chord && $key === Key::X => EditCommand::Cut,
            $chord && $key === Key::C => EditCommand::Copy,
            $chord && $key === Key::V => EditCommand::Paste,
            $chord && $key === Key::Z => $mods->shift ? EditCommand::Redo : EditCommand::Undo,
            $plain && ($key === Key::ENTER || $key === Key::NUMPAD_ENTER) => EditCommand::InsertNewline,
            $plain && $key === Key::TAB => $mods->shift ? EditCommand::InsertBacktab : EditCommand::InsertTab,
            $plain && $key === Key::ESCAPE => EditCommand::Cancel,
            default => null,
        };
    }
}
