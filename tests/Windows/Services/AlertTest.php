<?php

declare(strict_types=1);

use Surface\Contracts\Windows\Services\Alert;
use Surface\Contracts\Windows\Services\AlertStyle;
use Surface\Contracts\Windows\Services\OpenRequest;

it('defaults to one OK button that Enter and Esc both answer', function (): void {
    $alert = new Alert('Done.');

    expect($alert->buttons)->toBe(['OK'])
        ->and($alert->default)->toBe(0)
        ->and($alert->cancel)->toBe(0)
        ->and($alert->detail)->toBeNull()
        ->and($alert->style)->toBeNull()
        ->and($alert->suppression)->toBeNull()
        ->and($alert->title)->toBeNull();
});

it('keeps its style, suppression label and title', function (): void {
    $alert = new Alert('Delete?', style: AlertStyle::Critical, suppression: 'Don\'t ask again', title: 'Delete');

    expect($alert->style)->toBe(AlertStyle::Critical)
        ->and($alert->suppression)->toBe('Don\'t ask again')
        ->and($alert->title)->toBe('Delete')
        ->and(array_map(fn (AlertStyle $style): string => $style->value, AlertStyle::cases()))->toBe(['info', 'warning', 'critical']);
});

it('answers Esc with the last button unless a cancel is given', function (): void {
    expect((new Alert('Save?', null, ['Save', 'Discard', 'Cancel']))->cancel)->toBe(2)
        ->and((new Alert('Save?', null, ['Save', 'Discard', 'Cancel'], cancel: 1))->cancel)->toBe(1);
});

it('refuses buttons it cannot show', function (array $buttons, int $default, ?int $cancel, string $names): void {
    expect(fn () => new Alert('Save?', null, $buttons, $default, $cancel))
        ->toThrow(InvalidArgumentException::class, $names);
})->with([
    'no buttons' => [[], 0, null, '0 buttons'],
    'four buttons' => [['A', 'B', 'C', 'D'], 0, null, '4 buttons'],
    'default outside' => [['A', 'B', 'C'], 3, null, 'default 3'],
    'cancel outside' => [['A', 'B', 'C'], 0, -1, 'cancel -1'],
    'not a string' => [['OK', 2], 0, null, 'index 1'],
    'not a list' => [[1 => 'OK'], 0, null, 'a list'],
]);

it('refuses several folders at once', function (): void {
    expect(fn () => new OpenRequest(folders: true, multiple: true))->toThrow(InvalidArgumentException::class, 'multiple');
});
