<?php

declare(strict_types=1);

namespace Surface\Contracts\Windows\Input;

/**
 * The platform's editing binding for a key pressed on a focused canvas, as EditCommanded carries it.
 * Each move has a Select twin (the Shift form). Cut, Copy, Paste, Undo and Redo reach a canvas only
 * where no menu accelerator claims the key.
 */
enum EditCommand: string
{
    case MoveLeft = 'move-left';
    case MoveRight = 'move-right';
    case MoveUp = 'move-up';
    case MoveDown = 'move-down';
    case MoveWordLeft = 'move-word-left';
    case MoveWordRight = 'move-word-right';
    case MoveToLineStart = 'move-to-line-start';
    case MoveToLineEnd = 'move-to-line-end';
    case MoveToDocumentStart = 'move-to-document-start';
    case MoveToDocumentEnd = 'move-to-document-end';
    case PageUp = 'page-up';
    case PageDown = 'page-down';

    case SelectLeft = 'select-left';
    case SelectRight = 'select-right';
    case SelectUp = 'select-up';
    case SelectDown = 'select-down';
    case SelectWordLeft = 'select-word-left';
    case SelectWordRight = 'select-word-right';
    case SelectToLineStart = 'select-to-line-start';
    case SelectToLineEnd = 'select-to-line-end';
    case SelectToDocumentStart = 'select-to-document-start';
    case SelectToDocumentEnd = 'select-to-document-end';
    case SelectPageUp = 'select-page-up';
    case SelectPageDown = 'select-page-down';

    case DeleteBackward = 'delete-backward';
    case DeleteForward = 'delete-forward';
    case DeleteWordBackward = 'delete-word-backward';
    case DeleteWordForward = 'delete-word-forward';
    case DeleteToLineEnd = 'delete-to-line-end';
    case Yank = 'yank';

    case SelectAll = 'select-all';
    case Cut = 'cut';
    case Copy = 'copy';
    case Paste = 'paste';
    case Undo = 'undo';
    case Redo = 'redo';

    case InsertNewline = 'insert-newline';
    case InsertTab = 'insert-tab';
    case InsertBacktab = 'insert-backtab';
    case Cancel = 'cancel';
}
