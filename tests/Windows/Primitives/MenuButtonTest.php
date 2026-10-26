<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Windows\WindowException;
use Surface\Framebuffers\Native\NativeFullFramebuffer;
use Surface\Windows\Menus\ContextMenu;
use Venusian\Surface\Tests\Fixtures\FakeHost;

/*
 * Menu button: a label, an optional image, and a context menu that opens under it.
 */

it('holds its label and its menu parsed as a context menu', function (): void {
    $button = (new FakeHost('main'))->column('m')->menuButton('mb', 'Ignore case', [['label' => 'None'], ['label' => 'All']]);

    expect($button->label())->toBe('Ignore case')
        ->and($button->menu())->toBeInstanceOf(ContextMenu::class)
        ->and(array_keys($button->menu()->items()))->toBe(['none', 'all']);
});

it('refuses a menu a context menu refuses', function (): void {
    $button = (new FakeHost('main'))->column('m')->menuButton('mb', 'Edit', [['label' => 'None']]);

    expect(fn () => $button->setMenu([['label' => 'Copy', 'role' => 'copy']]))->toThrow(WindowException::class)
        ->and(fn () => (new FakeHost('w'))->column('m')->menuButton('mb', 'x', [['label' => 'Copy', 'role' => 'copy']]))->toThrow(WindowException::class);
});

it('applies label, image, menu and bordered', function (): void {
    $button = (new FakeHost('main'))->column('m')->menuButton('mb', 'Ignore case', [['label' => 'None']]);

    $button->setLabel('Case');
    $button->setImage(new NativeFullFramebuffer(FormatSpec::rgba8(), 16, 16), 2.0);
    $button->setMenu(ContextMenu::parse([['label' => 'All']]));
    $button->setBordered(false);

    expect($button->label())->toBe('Case')
        ->and($button->log)->toContain('label:Case')
        ->and($button->log)->toContain('image:16x16,2')
        ->and($button->log)->toContain('menu:all')
        ->and($button->log)->toContain('bordered:0')
        ->and($button->isBordered())->toBeFalse();
});

it('refuses an image scale that is not positive', function (): void {
    $button = (new FakeHost('main'))->column('m')->menuButton('mb', 'x', [['label' => 'None']]);

    expect(fn () => $button->setImage(new NativeFullFramebuffer(FormatSpec::rgba8(), 1, 1), -1.0))
        ->toThrow(WindowException::class, 'Image scale must be positive.');
});
