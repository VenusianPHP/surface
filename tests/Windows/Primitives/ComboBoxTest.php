<?php

declare(strict_types=1);

use Surface\Contracts\Windows\WindowException;
use Venusian\Surface\Tests\Fixtures\FakeHost;

/*
 * Combo box, toolkit-neutral: the fake native reports $field_focus as the field's focus.
 */

it('starts with its value and options and no completions', function (): void {
    $combo = (new FakeHost('main'))->column('m')->comboBox('c', '/tmp', ['/Users', '/Volumes']);

    expect($combo->value())->toBe('/tmp')
        ->and($combo->options())->toBe(['/Users', '/Volumes'])
        ->and($combo->completions())->toBe([]);
});

it('refuses options or completions that are not a list of strings', function (): void {
    $combo = (new FakeHost('main'))->column('m')->comboBox('c');

    expect(fn () => $combo->setOptions(['a', 1]))->toThrow(WindowException::class, 'Combo options must be a list of strings.')
        ->and(fn () => $combo->setCompletions(['x' => 'a']))->toThrow(WindowException::class, 'Combo completions must be a list of strings.')
        ->and(fn () => (new FakeHost('w'))->column('m')->comboBox('c', '', [1]))->toThrow(WindowException::class, 'Combo options must be a list of strings.');
});

it('keeps completions while the field has focus and drops them while it has none', function (): void {
    $combo = (new FakeHost('main'))->column('m')->comboBox('c');

    $combo->field_focus = true;
    $combo->setCompletions(['/Users']);
    expect($combo->completions())->toBe(['/Users'])
        ->and($combo->log)->toContain('completions:/Users');

    $combo->field_focus = false;
    $combo->setCompletions(['/Volumes']);
    expect($combo->completions())->toBe([]);
});

it('clears completions and applies the value on setValue', function (): void {
    $combo = (new FakeHost('main'))->column('m')->comboBox('c');
    $combo->field_focus = true;
    $combo->setCompletions(['/Users']);

    $combo->setValue('x');

    expect($combo->value())->toBe('x')
        ->and($combo->completions())->toBe([])
        ->and($combo->log)->toContain('value:x');
});

it('records a choice, an edit and a hide from the native', function (): void {
    $combo = (new FakeHost('main'))->column('m')->comboBox('c');
    $combo->field_focus = true;
    $combo->setCompletions(['/Users/a', '/Users/b']);

    $combo->nativeEdited('/Us');
    expect($combo->value())->toBe('/Us')->and($combo->completions())->toBe(['/Users/a', '/Users/b']);

    $combo->nativeChose('/Users/a');
    expect($combo->value())->toBe('/Users/a')->and($combo->completions())->toBe([]);

    $combo->setCompletions(['/Users/b']);
    $combo->nativeHidden();
    expect($combo->completions())->toBe([]);
});

it('refuses every setter once removed', function (string $call): void {
    $combo = (new FakeHost('main'))->column('m')->comboBox('c');
    $combo->remove();

    expect(fn () => match ($call) {
        'value' => $combo->setValue('x'),
        'options' => $combo->setOptions([]),
        'completions' => $combo->setCompletions([]),
    })->toThrow(WindowException::class);
})->with(['value', 'options', 'completions']);
