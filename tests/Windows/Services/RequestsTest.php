<?php

declare(strict_types=1);

use Surface\Contracts\Windows\Services\OpenRequest;
use Surface\Contracts\Windows\Services\SaveRequest;

it('keeps its filters in order and hidden files off by default', function (string $kind): void {
    $filters = ['Text' => ['*.txt', '*.md'], 'Images' => ['*.png']];
    $request = $kind === 'open' ? new OpenRequest(filters: $filters, showHidden: true) : new SaveRequest(filters: $filters, showHidden: true);
    $plain = $kind === 'open' ? new OpenRequest() : new SaveRequest();

    expect($request->filters)->toBe($filters)
        ->and(array_keys($request->filters))->toBe(['Text', 'Images'])
        ->and($request->showHidden)->toBeTrue()
        ->and($plain->filters)->toBe([])
        ->and($plain->showHidden)->toBeFalse();
})->with(['open', 'save']);

it('refuses filters on a folder request', function (): void {
    expect(fn () => new OpenRequest(folders: true, filters: ['Text' => ['*.txt']]))->toThrow(InvalidArgumentException::class, 'folders');
});

it('refuses a filter it cannot show', function (string $kind, array $filters, string $names): void {
    $make = $kind === 'open' ? fn () => new OpenRequest(filters: $filters) : fn () => new SaveRequest(filters: $filters);

    expect($make)->toThrow(InvalidArgumentException::class, $names);
})->with([
    'open, empty name' => ['open', ['' => ['*.txt']], 'name'],
    'open, a number for a name' => ['open', [0 => ['*.txt']], 'name'],
    'open, no patterns' => ['open', ['Text' => []], "'Text'"],
    'open, patterns not a list' => ['open', ['Text' => [1 => '*.txt']], "'Text'"],
    'open, a pattern not a string' => ['open', ['Text' => ['*.txt', 2]], "'Text'"],
    'open, an empty pattern' => ['open', ['Text' => ['']], "'Text'"],
    'save, empty name' => ['save', ['' => ['*.txt']], 'name'],
    'save, a pattern not a string' => ['save', ['Text' => [2]], "'Text'"],
]);
