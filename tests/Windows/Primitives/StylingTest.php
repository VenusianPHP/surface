<?php

declare(strict_types=1);

use Surface\Contracts\Windows\Styling\FontMetrics;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\FontWeight;
use Surface\Contracts\Windows\Styling\ScrollPosition;
use Surface\Contracts\Windows\Styling\TextAlignment;
use Surface\Contracts\Windows\Styling\TextRangeStyle;
use Surface\NutsAndBolts\Color;

it('maps each font weight to its css weight', function (): void {
    expect(array_map(fn (FontWeight $w): int => $w->toCssWeight(), FontWeight::cases()))->toBe([300, 400, 500, 600, 700, 900]);
});

it('defaults a font to the regular weight of the system family and refuses a size that is not positive', function (): void {
    $font = new FontSpec(13.0);

    expect($font->weight)->toBe(FontWeight::REGULAR)
        ->and($font->family)->toBeNull()
        ->and((new FontSpec(18.0, FontWeight::BOLD, 'Menlo'))->family)->toBe('Menlo')
        ->and(fn () => new FontSpec(0.0))->toThrow(InvalidArgumentException::class, 'positive')
        ->and(fn () => new FontSpec(-2.0))->toThrow(InvalidArgumentException::class, 'positive')
        ->and(fn () => new FontSpec(NAN))->toThrow(InvalidArgumentException::class, 'positive')
        ->and(fn () => new FontSpec(INF))->toThrow(InvalidArgumentException::class, 'positive');
});

it('aligns text left, center or right', function (): void {
    expect(array_map(fn (TextAlignment $a): string => $a->value, TextAlignment::cases()))->toBe(['left', 'center', 'right']);
});

it('asks for the fixed-pitch face with monospace and keeps the family when one is named', function (): void {
    expect((new FontSpec(12.0))->monospace)->toBeFalse()
        ->and((new FontSpec(12.0, monospace: true))->monospace)->toBeTrue()
        ->and((new FontSpec(12.0, family: 'Menlo', monospace: true))->family)->toBe('Menlo');
});

it('asks for an upright face unless italic', function (): void {
    expect((new FontSpec(12.0))->italic)->toBeFalse()
        ->and((new FontSpec(12.0, italic: true))->italic)->toBeTrue();
});

it('keeps font metrics in points and refuses a negative or infinite one', function (): void {
    $metrics = new FontMetrics(10.0, 3.0, 15.0, 7.0, true);

    expect([$metrics->ascent, $metrics->descent, $metrics->lineHeight, $metrics->averageCharWidth, $metrics->fixedPitch])->toBe([10.0, 3.0, 15.0, 7.0, true])
        ->and(fn () => new FontMetrics(10.0, -3.0, 15.0, 7.0, false))->toThrow(InvalidArgumentException::class, 'not negative')
        ->and(fn () => new FontMetrics(INF, 3.0, 15.0, 7.0, false))->toThrow(InvalidArgumentException::class, 'finite')
        ->and(fn () => new FontMetrics(10.0, 3.0, NAN, 7.0, false))->toThrow(InvalidArgumentException::class, 'finite');
});

it('refuses a range style with negative values or with no colour and no flag', function (): void {
    $style = new TextRangeStyle(1, 2, 3, foreground: Color::hex('#ff0000'));

    expect([$style->line, $style->start, $style->length])->toBe([1, 2, 3])
        ->and([$style->bold, $style->italic, $style->underline, $style->strikeout, $style->hasFlags()])->toBe([false, false, false, false, false])
        ->and(fn () => new TextRangeStyle(-1, 0, 1, foreground: Color::hex('#ff0000')))->toThrow(InvalidArgumentException::class, 'not negative')
        ->and(fn () => new TextRangeStyle(0, 0, -1, background: Color::hex('#ff0000')))->toThrow(InvalidArgumentException::class, 'not negative')
        ->and(fn () => new TextRangeStyle(0, 0, 1))->toThrow(InvalidArgumentException::class, 'a colour or a flag');
});

it('takes a range style with only flags', function (): void {
    foreach (['bold', 'italic', 'underline', 'strikeout'] as $flag) {
        $style = new TextRangeStyle(0, 0, 3, ...[$flag => true]);

        expect($style->{$flag})->toBeTrue()
            ->and($style->hasFlags())->toBeTrue()
            ->and($style->foreground)->toBeNull()
            ->and($style->background)->toBeNull();
    }
});

it('measures a scroll position as content fractions and turns a fraction back into an offset', function (): void {
    $at = ScrollPosition::of(0.0, 100.0, 100.0, 250.0, 100.0, 1000.0);

    expect([$at->x, $at->y, $at->width, $at->height])->toBe([0.0, 0.25, 1.0, 0.1])
        ->and(ScrollPosition::of(0.0, 50.0, 0.0, 0.0, 50.0, 0.0))->toEqual(new ScrollPosition(0.0, 0.0, 1.0, 1.0))
        ->and(ScrollPosition::of(0.0, 10.0, 100.0, 990.0, 100.0, 1000.0)->y)->toBe(0.9)
        ->and(ScrollPosition::offset(0.25, 100.0, 1000.0))->toBe(250.0)
        ->and(ScrollPosition::offset(1.0, 100.0, 1000.0))->toBe(900.0)
        ->and(ScrollPosition::offset(0.5, 100.0, 50.0))->toBe(0.0)
        ->and(fn () => new ScrollPosition(0.0, 1.5, 1.0, 1.0))->toThrow(InvalidArgumentException::class, '0..1')
        ->and(fn () => new ScrollPosition(INF, 0.0, 1.0, 1.0))->toThrow(InvalidArgumentException::class, '0..1');
});

it('rounds corners to the radius it is given, and refuses a negative one', function (): void {
    $card = (new \Venusian\Surface\Tests\Fixtures\FakeHost('main'))->column('m')->column('card');
    $card->log = [];

    expect($card->cornerRadius())->toBe(0.0)
        ->and($card->setCornerRadius(8.0)->cornerRadius())->toBe(8.0)
        ->and($card->log)->toBe(['corner:8'])
        ->and(fn () => $card->setCornerRadius(-1.0))->toThrow(\Surface\Contracts\Windows\WindowException::class);
});
