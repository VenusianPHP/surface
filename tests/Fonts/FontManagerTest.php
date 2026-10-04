<?php

use Surface\Contracts\Fonts\FontException;
use Surface\Contracts\Fonts\FontRegistry;
use Surface\Contracts\Fonts\GFXFont;
use Surface\Fonts\ClassicFont;
use Surface\Fonts\FontManager;

it('registers classic first and answers it as the default', function () {
    $fonts = new FontManager();

    expect($fonts)->toBeInstanceOf(FontRegistry::class)
        ->and($fonts->defaultSlug())->toBe('classic')
        ->and($fonts->has('classic'))->toBeTrue()
        ->and($fonts->face())->toBeInstanceOf(ClassicFont::class)
        ->and($fonts->face())->toBe($fonts->face('classic'))
        ->and($fonts->slugs())->toBe(['classic']);
});

it('extend registers a concrete face under a normalised slug', function () {
    $stub = new class extends GFXFont {};
    $fonts = (new FontManager())->extend(' Tiny-HUD ', $stub::class);

    expect($fonts->has('tiny-hud'))->toBeTrue()
        ->and($fonts->face('TINY-HUD'))->toBeInstanceOf($stub::class)
        ->and($fonts->face('tiny-hud'))->toBe($fonts->face('tiny-hud'))
        ->and($fonts->slugs())->toBe(['classic', 'tiny-hud']);
});

it('refuses a class that is not a concrete GFXFont', function () {
    expect(fn () => (new FontManager())->extend('x', stdClass::class))->toThrow(FontException::class)
        ->and(fn () => (new FontManager())->extend('x', GFXFont::class))->toThrow(FontException::class)
        ->and(fn () => (new FontManager())->extend('x', 'Nope\\Missing'))->toThrow(FontException::class);
});

it('throws for an unknown slug', function () {
    expect(fn () => (new FontManager())->face('ghost'))->toThrow(FontException::class);
});

it('reads the default and enabled faces from config, skipping disabled entries', function () {
    $on = new class extends GFXFont {};
    $off = new class extends GFXFont {};
    $fonts = new FontManager([
        'default' => 'HUD',
        'faces' => [
            'hud' => ['class' => $on::class, 'enabled' => true],
            'dim' => ['class' => $off::class, 'enabled' => false],
        ],
    ]);

    expect($fonts->defaultSlug())->toBe('hud')
        ->and($fonts->face())->toBeInstanceOf($on::class)
        ->and($fonts->has('dim'))->toBeFalse()
        ->and($fonts->has('classic'))->toBeTrue();
});

it('re-extending a slug drops the cached instance', function () {
    $a = new class extends GFXFont {};
    $b = new class extends GFXFont {};
    $fonts = (new FontManager())->extend('x', $a::class);
    $first = $fonts->face('x');
    $fonts->extend('x', $b::class);

    expect($fonts->face('x'))->toBeInstanceOf($b::class)->and($fonts->face('x'))->not->toBe($first);
});
