<?php

use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Contracts\NativeWindows\Views\FontWeight;
use Surface\Contracts\NativeWindows\WindowableException;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('parses hex colours in all three widths', function () {
    expect(Color::hex('#f50')->red)->toBe(1.0)
        ->and(Color::hex('ff5500')->green)->toEqual(0x55 / 255)
        ->and(Color::hex('#ff550080')->alpha)->toEqual(0x80 / 255)
        ->and(Color::hex('#f50')->toCss())->toBe('rgba(255, 85, 0, 1)');
});

it('rejects a malformed hex colour', function () {
    expect(fn () => Color::hex('#zzz'))->toThrow(WindowableException::class);
});

it('routes typed setters through the engine hooks and remembers', function () {
    $label = (new FakeWindow('main'))->label('t', 'Hi', 0, 0, 1, 1);

    $label->setTextColor(Color::hex('#ff5500'))->setFont(24.0, FontWeight::BOLD);

    expect($label->applied_text_colors[0]->red)->toBe(1.0)
        ->and($label->applied_fonts[0]->size)->toBe(24.0)
        ->and($label->applied_fonts[0]->weight)->toBe(FontWeight::BOLD);
});

it('textCSS routes recognised declarations and ignores the rest', function () {
    $label = (new FakeWindow('main'))->label('t', 'Hi', 0, 0, 1, 1);

    $label->textCSS('color: #ff5500; font-size: 24px; font-weight: bold; text-shadow: 1px 1px; background-color: #333');

    expect($label->applied_text_colors)->toHaveCount(1)
        ->and($label->applied_fonts)->toHaveCount(2)   // size then weight, merged spec
        ->and(end($label->applied_fonts)->size)->toBe(24.0)
        ->and(end($label->applied_fonts)->weight)->toBe(FontWeight::BOLD)
        ->and($label->applied_backgrounds[0]->toCss())->toBe('rgba(51, 51, 51, 1)');
});

it('later font declarations merge with earlier ones instead of resetting', function () {
    $button = (new FakeWindow('main'))->button('b', 'Go', 0, 0, 1, 1);

    $button->setFont(18.0)->textCSS('font-weight: 600');

    $font = end($button->applied_fonts);
    expect($font->size)->toBe(18.0)
        ->and($font->weight)->toBe(FontWeight::SEMIBOLD);
});

it('numeric css weights bucket into the enum', function () {
    $label = (new FakeWindow('main'))->label('t', 'Hi', 0, 0, 1, 1);
    $label->textCSS('font-weight: 900');

    expect(end($label->applied_fonts)->weight)->toBe(FontWeight::BLACK);
});

it('setBackground works on any view through the base hook', function () {
    $button = (new FakeWindow('main'))->button('b', 'Go', 0, 0, 1, 1);

    $button->setBackground(Color::hex('#222'));

    expect($button->applied_backgrounds)->toHaveCount(1);
});

it('a font change on a hugged view re-measures and re-centres', function () {
    $window = new FakeWindow('main');
    $window->content_size = [400, 600];
    $label = $window->label('t', 'Hi', 0, 0, 1, 1)->hug()->center();
    $label->natural_size = [300, 70];   // what the bigger font will measure

    $label->setFont(64.0, FontWeight::BOLD);

    expect($label->frame())->toBe(['x' => 50, 'y' => 265, 'width' => 300, 'height' => 70]);
});

it('a text change on a hugged view re-measures too', function () {
    $window = new FakeWindow('main');
    $label = $window->label('t', 'Hi', 10, 10, 1, 1)->hug();
    $label->natural_size = [220, 20];

    $label->setText('Much longer text');

    expect($label->frame()['width'])->toBe(220);
});

it('a font change on a fixed-size view leaves the frame alone', function () {
    $window = new FakeWindow('main');
    $label = $window->label('t', 'Hi', 10, 10, 100, 20);
    $label->natural_size = [300, 70];

    $label->setFont(64.0);

    expect($label->frame())->toBe(['x' => 10, 'y' => 10, 'width' => 100, 'height' => 20]);
});
