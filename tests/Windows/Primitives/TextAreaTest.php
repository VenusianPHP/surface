<?php

declare(strict_types=1);

use Surface\Contracts\Windows\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Windows\Styling\ScrollPosition;
use Surface\Contracts\Windows\Styling\TextRangeStyle;
use Surface\Contracts\Windows\WindowException;
use Surface\NutsAndBolts\Color;
use Surface\Windows\Primitives\TextLines;
use Venusian\Surface\Tests\Fixtures\FakeHost;

function areaHost(): TKPrimitiveGroup
{
    return (new FakeHost('main'))->column('m');
}

it('counts lines and maps code points to UTF-16 offsets both ways', function (): void {
    $lines = new TextLines("ab\ncé😀d\n\nlast");

    expect($lines->count())->toBe(4)
        ->and([$lines->length(1), $lines->utf16Length(1)])->toBe([4, 5])
        ->and(array_map(fn (int $l): int => $lines->utf16Start($l), [0, 1, 2, 3]))->toBe([0, 3, 9, 10])
        ->and($lines->utf16Column(1, 3))->toBe(4)
        ->and($lines->utf16Total())->toBe(14)
        ->and(array_map(fn (int $o): int => $lines->lineAtUtf16($o), [0, 2, 3, 8, 9, 10, 14]))->toBe([0, 0, 1, 1, 2, 3, 3])
        ->and((new TextLines(''))->count())->toBe(1);
});

it('ends lines where the toolkits do: CRLF, CR, LF and U+2029, each at its own UTF-16 width', function (): void {
    $lines = new TextLines("ab\r\ncd\ref\u{2029}g");

    expect($lines->count())->toBe(4)
        ->and(array_map(fn (int $l): int => $lines->utf16Start($l), [0, 1, 2, 3]))->toBe([0, 4, 7, 10])
        ->and($lines->lineAtUtf16(3))->toBe(0)
        ->and($lines->length(0))->toBe(2)
        ->and($lines->utf16Total())->toBe(11);
});

it('normalises line endings, styles lines and ranges inside the text, and clears both with a new value', function (): void {
    $area = areaHost()->textArea('t', "one\r\ntwo\rthree");
    $red = Color::hex('#ff0000');

    $area->setLineBackgrounds([9 => $red, 1 => $red])
        ->setRangeStyles([
            new TextRangeStyle(2, 3, 9, foreground: $red),      // clipped to 'ee'
            new TextRangeStyle(0, 3, 2, background: $red),      // starts at the line end: dropped
            new TextRangeStyle(7, 0, 1, foreground: $red),      // no such line: dropped
        ]);

    expect($area->value())->toBe("one\ntwo\nthree")
        ->and($area->lineCount())->toBe(3)
        ->and($area->log)->toBe(['lines:1=#ff0000', 'ranges:2:3+2']);

    $area->setValue("x\r\ny");
    expect($area->value())->toBe("x\ny")
        ->and($area->log)->toBe(['lines:1=#ff0000', 'ranges:2:3+2', 'value:x'."\n".'y|0,0', 'lines:', 'ranges:']);

    $area->setValue('z');
    expect($area->log)->toHaveCount(6);
});

it('records what the user typed without clearing styles', function (): void {
    $area = areaHost()->textArea('t', "a\nb");
    $area->setLineBackgrounds([1 => Color::hex('#00ff00')]);
    $area->nativeValueChanged("a\nb\nc");

    expect($area->lineCount())->toBe(3)
        ->and($area->log)->toBe(['lines:1=#00ff00']);
});

it('refuses line backgrounds and range styles of the wrong shape', function (): void {
    $area = areaHost()->textArea('t', 'x');

    expect(fn () => $area->setLineBackgrounds([-1 => Color::hex('#000000')]))->toThrow(WindowException::class, "Text area 'm.t' line backgrounds map 0-based line numbers to Colors.")
        ->and(fn () => $area->setLineBackgrounds(['a' => Color::hex('#000000')]))->toThrow(WindowException::class)
        ->and(fn () => $area->setLineBackgrounds([0 => '#000000']))->toThrow(WindowException::class)
        ->and(fn () => $area->setRangeStyles(['x']))->toThrow(WindowException::class, "Text area 'm.t' range styles must be TextRangeStyle objects.")
        ->and($area->log)->toBe([]);
});

it('reads, clamps and applies selection and scrolling, and stores read-only, wrap and scroll watching', function (): void {
    $area = areaHost()->textArea('t', "a\nb\nc");
    $area->selection = [1, 9];
    $area->position = new ScrollPosition(0.0, 0.25, 1.0, 0.5);

    $area->setReadOnly(true)->setWrap(false)->watchScroll()
        ->selectLines(2, 7)->scrollTo(-1.0, 2.0)->scrollToLine(40);

    expect([$area->isReadOnly(), $area->wraps(), $area->isWatchingScroll()])->toBe([true, false, true])
        ->and($area->selectedLines())->toBe([1, 2])
        ->and($area->caretLine())->toBe(1)
        ->and($area->scrollPosition())->toBe($area->position)
        ->and($area->log)->toBe(['readonly:1', 'wrap:0', 'select:2-2', 'scroll:0,1', 'line:2'])
        ->and(fn () => $area->selectLines(2, 1))->toThrow(WindowException::class, "Text area 'm.t' selects lines 0 <= first <= last, got 2-1.")
        ->and(fn () => $area->scrollToLine(-1))->toThrow(WindowException::class, "Text area 'm.t' scrolls to a line from 0, got -1.")
        ->and(fn () => $area->scrollTo(NAN, 0.0))->toThrow(WindowException::class, "Text area 'm.t' scroll position must be finite.");
});
