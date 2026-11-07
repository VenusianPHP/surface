<?php

declare(strict_types=1);

use Surface\Fonts\Extended\FontCatalog;
use Surface\Fonts\Extended\FontFile;

/*
 * FontCatalog finds fonts through fontconfig: one match per pattern, and a
 * fallback per codepoint from the pattern's sorted list.
 */

beforeEach(function (): void {
    if (! extension_loaded('fontconfig')) {
        $this->markTestSkipped('ext-fontconfig is not loaded (run with php 8.4).');
    }
    $this->catalog = new FontCatalog();
});

afterEach(fn () => isset($this->catalog) && $this->catalog->close());

it('matches a family name to a readable font file', function (): void {
    $file = $this->catalog->match('sans-serif');

    expect($file)->toBeInstanceOf(FontFile::class)
        ->and(is_readable($file->path))->toBeTrue()
        ->and($file->index)->toBeGreaterThanOrEqual(0);
});

it('matches monospace to a different file', function (): void {
    expect($this->catalog->match('monospace'))->not->toEqual($this->catalog->match('sans-serif'));
});

it('falls back to a font that has the codepoint', function (): void {
    $file = $this->catalog->fallback('sans-serif', 0x4E2D);

    expect($file)->not->toBeNull()
        ->and($this->catalog->covers($file, 0x4E2D))->toBeTrue();
});

it('has no fallback for a codepoint no font covers', function (): void {
    expect($this->catalog->fallback('sans-serif', 0x10FFFD))->toBeNull();
});

it('sorts each pattern once', function (): void {
    $this->catalog->fallback('sans-serif', 0x4E2D);
    $this->catalog->fallback('sans-serif', 0x4E2D);
    $this->catalog->fallback('sans-serif', 0x3042);

    expect($this->catalog->sorts)->toBe(1);
});

it('closes twice without throwing', function (): void {
    $this->catalog->fallback('sans-serif', 0x41);
    $this->catalog->close();
    $this->catalog->close();
})->throwsNoExceptions();
