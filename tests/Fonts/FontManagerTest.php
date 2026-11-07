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

function ttConfig(): array
{
    return ['faces' => [
        'classic' => ['class' => ClassicFont::class, 'enabled' => true],
        'ui' => ['pattern' => 'sans-serif', 'size' => 13, 'enabled' => true],
    ]];
}

it('answers a TrueType face from config, one instance per slug', function () {
    $fonts = new FontManager(ttConfig(), fn () => new Venusian\Surface\Tests\Support\Fakes\FakeFontDriver);
    $ui = $fonts->face('ui');

    expect($ui)->toBeInstanceOf(Surface\Contracts\Fonts\TrueTypeFont::class)
        ->and($ui->pattern)->toBe('sans-serif')
        ->and($ui->size)->toBe(13.0)
        ->and($fonts->face('UI'))->toBe($ui);
});

it('registers a TrueType face with truetype()', function () {
    $fonts = (new FontManager([], fn () => new Venusian\Surface\Tests\Support\Fakes\FakeFontDriver))->truetype('Mono', 'monospace', 11);

    expect($fonts->has('mono'))->toBeTrue()
        ->and($fonts->face('mono')->pattern)->toBe('monospace')
        ->and($fonts->face('mono')->size)->toBe(11.0);
});

it('asks for the driver only on the first TrueType face', function () {
    $asked = 0;
    $fonts = new FontManager(ttConfig(), function () use (&$asked) {
        $asked++;

        return new Venusian\Surface\Tests\Support\Fakes\FakeFontDriver;
    });
    $fonts->face('classic');
    expect($asked)->toBe(0);
    $fonts->face('ui');
    $fonts->truetype('mono', 'monospace', 11)->face('mono');
    expect($asked)->toBe(1);
});

it('keeps bitmap fonts working without the font exts', function () {
    $fonts = new FontManager(ttConfig(), fn () => throw FontException::extensionsMissing());

    expect($fonts->face('classic'))->toBeInstanceOf(ClassicFont::class);
    try {
        $fonts->face('ui');
        $this->fail('no exception');
    } catch (FontException $e) {
        expect($e->getMessage())->toContain('ext-freetype')->toContain('ext-harfbuzz')->toContain('ext-fontconfig');
    }
    expect(fn () => (new FontManager(ttConfig()))->face('ui'))->toThrow(FontException::class, 'ext-freetype');
});

it('lists TrueType slugs with the bitmap ones', function () {
    expect((new FontManager(ttConfig()))->slugs())->toBe(['classic', 'ui']);
});
