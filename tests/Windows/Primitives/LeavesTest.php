<?php

declare(strict_types=1);

use Surface\Contracts\Windows\Primitives\ImageScaling;
use Surface\Contracts\Windows\Primitives\TableColumn;
use Surface\Contracts\Windows\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\TextAlignment;
use Surface\Contracts\Windows\WindowException;
use Surface\NutsAndBolts\Color;
use Venusian\Surface\Tests\Fixtures\FakeHost;

function leafHost(): TKPrimitiveGroup
{
    return (new FakeHost('main'))->column('m');
}

it('styles a label: wrap, alignment, font, colour', function (): void {
    $label = leafHost()->label('l', 'x');
    $label->setWrap(true)->setAlignment(TextAlignment::CENTER)->setFont(new FontSpec(14.0))->setTextColor(Color::rgb(255, 0, 0))->setTextColor(null);

    expect($label->log)->toBe(['wrap:1', 'alignment:center', 'font:14', 'color:#ff0000', 'color:none']);
});

it('styles a button: font, colour', function (): void {
    $button = leafHost()->button('b', 'Go');
    $button->setFont(new FontSpec(11.0))->setTextColor(Color::hex('#00f'));

    expect($button->log)->toBe(['font:11', 'color:#0000ff']);
});

it('sets an image file and scaling', function (): void {
    $image = leafHost()->image('i', '/tmp/a.png');
    $image->setScaling(ImageScaling::FILL)->setFile(null);

    expect($image->file())->toBeNull()
        ->and($image->log)->toBe(['scaling:fill', 'file:none'])
        ->and(leafHost()->image('j')->file())->toBeNull();
});

it('keeps a separator orientation', function (): void {
    $host = leafHost();

    expect($host->separator('s')->isHorizontal())->toBeTrue()
        ->and($host->separator('v', horizontal: false)->isHorizontal())->toBeFalse();
});

it('starts and stops a spinner change-only', function (): void {
    $spinner = leafHost()->spinner('s');
    $spinner->start()->start();

    expect($spinner->isSpinning())->toBeTrue();
    $spinner->stop()->stop();

    expect($spinner->isSpinning())->toBeFalse()
        ->and($spinner->log)->toBe(['spinning:1', 'spinning:0']);
});

it('holds a progress fraction or indeterminate, refusing anything outside 0..1', function (): void {
    $bar = leafHost()->progressBar('p');
    $bar->setFraction(0.25)->setFraction(null);

    expect($bar->fraction())->toBeNull()
        ->and($bar->log)->toBe(['fraction:0.25', 'fraction:none'])
        ->and(fn () => $bar->setFraction(1.5))->toThrow(WindowException::class, '0..1')
        ->and(fn () => $bar->setFraction(-0.1))->toThrow(WindowException::class, '0..1')
        ->and(fn () => leafHost()->progressBar('q', 2.0))->toThrow(WindowException::class, '0..1')
        ->and(fn () => leafHost()->progressBar('r', NAN))->toThrow(WindowException::class, '0..1')
        ->and(fn () => $bar->setFraction(NAN))->toThrow(WindowException::class, '0..1');
});

it('edits a text input and records what the user typed without writing it back', function (): void {
    $input = leafHost()->textInput('t', 'a', 'Name', secret: true);
    $input->setValue('b')->setPlaceholder(null)->setFont(new FontSpec(12.0))->setTextColor(null);
    $input->nativeValueChanged('typed');

    expect($input->value())->toBe('typed')
        ->and($input->placeholder())->toBeNull()
        ->and($input->isSecret())->toBeTrue()
        ->and($input->log)->toBe(['value:b', 'placeholder:none', 'font:12', 'color:none']);
});

it('edits a text area and records what the user typed without writing it back', function (): void {
    $area = leafHost()->textArea('t', "one\ntwo");
    $area->setValue('three')->setFont(new FontSpec(9.0))->setTextColor(Color::rgb(0, 0, 0));
    $area->nativeValueChanged('four');

    expect($area->value())->toBe('four')
        ->and($area->log)->toBe(['value:three', 'font:9', 'color:#000000']);
});

it('checks a checkbox from code and records a native toggle', function (): void {
    $box = leafHost()->checkbox('c', 'Remember', true);
    $box->setLabel('Keep')->setChecked(false);

    expect($box->isChecked())->toBeFalse()
        ->and($box->label())->toBe('Keep');
    $box->nativeToggled(true);

    expect($box->isChecked())->toBeTrue()
        ->and($box->log)->toBe(['label:Keep', 'checked:0']);
});

it('switches a toggle from code and records a native toggle', function (): void {
    $toggle = leafHost()->toggle('t');
    $toggle->setOn(true);
    $toggle->nativeToggled(false);

    expect($toggle->isOn())->toBeFalse()
        ->and($toggle->log)->toBe(['on:1']);
});

it('presses a toggle button from code and records a native toggle', function (): void {
    $button = leafHost()->toggleButton('t', 'Bold');
    $button->setPressed(true)->setLabel('B');
    $button->nativeToggled(false);

    expect($button->isPressed())->toBeFalse()
        ->and($button->label())->toBe('B')
        ->and($button->log)->toBe(['pressed:1', 'label:B']);
});

it('clamps a slider into its range and refuses an empty range', function (): void {
    $slider = leafHost()->slider('s', 0.0, 10.0, 5.0);
    $slider->setValue(12.0);

    expect($slider->value())->toBe(10.0);
    $slider->setRange(0.0, 4.0);

    expect([$slider->min(), $slider->max(), $slider->value()])->toBe([0.0, 4.0, 4.0])
        ->and($slider->log)->toBe(['value:10', 'range:0,4', 'value:4'])
        ->and(fn () => $slider->setRange(5.0, 5.0))->toThrow(WindowException::class, 'below')
        ->and(fn () => leafHost()->slider('t', 3.0, 1.0, 2.0))->toThrow(WindowException::class, 'below')
        ->and(leafHost()->slider('u', 0.0, 1.0, 9.0)->value())->toBe(1.0)
        ->and(fn () => leafHost()->slider('v', 0.0, NAN, 0.0))->toThrow(WindowException::class, 'finite')
        ->and(fn () => leafHost()->slider('w', -INF, 1.0, 0.0))->toThrow(WindowException::class, 'finite')
        ->and(fn () => leafHost()->slider('x', 0.0, 1.0, NAN))->toThrow(WindowException::class, 'finite')
        ->and(fn () => $slider->setValue(NAN))->toThrow(WindowException::class, 'finite');
    $slider->nativeValueChanged(2.5);

    expect($slider->value())->toBe(2.5)
        ->and($slider->log)->toHaveCount(3);
});

it('selects dropdown options in range and keeps the selection through new options when it can', function (): void {
    $dropdown = leafHost()->dropdown('d', ['a', 'b', 'c'], 1);

    expect($dropdown->selectedOption())->toBe('b')
        ->and(fn () => $dropdown->select(9))->toThrow(WindowException::class, 'out of range')
        ->and(fn () => leafHost()->dropdown('e', ['a'], 3))->toThrow(WindowException::class, 'out of range')
        ->and(fn () => leafHost()->dropdown('f', ['a', 7]))->toThrow(WindowException::class, 'list of strings');

    $dropdown->select(2)->setOptions(['x', 'y', 'z', 'w']);
    expect($dropdown->selected())->toBe(2);
    $dropdown->setOptions(['only']);
    expect($dropdown->selected())->toBe(0)
        ->and($dropdown->selectedOption())->toBe('only');
    $dropdown->setOptions([]);
    expect($dropdown->selected())->toBe(-1)
        ->and($dropdown->selectedOption())->toBeNull()
        ->and(leafHost()->dropdown('g', [])->selected())->toBe(-1)
        ->and($dropdown->log)->toBe(['selected:2', 'options:x|y|z|w', 'selected:2', 'options:only', 'selected:0', 'options:', 'selected:-1']);

    $dropdown->setOptions(['p', 'q']);
    $dropdown->nativeSelected(1);
    expect($dropdown->selectedOption())->toBe('q')
        ->and(end($dropdown->log))->toBe('selected:0');
});

it('holds a datepicker date and records a native change', function (): void {
    $picker = leafHost()->datepicker('d');
    $picker->setDate(new DateTimeImmutable('2026-10-02'));
    $picker->nativeDateChanged($chosen = new DateTimeImmutable('2026-12-25'));

    expect($picker->date())->toBe($chosen)
        ->and($picker->log)->toBe(['date:2026-10-02']);
});

it('normalises table rows to every column, in column order, missing keys as empty', function (): void {
    $columns = [new TableColumn('title', 'Title'), new TableColumn('year', 'Year')];
    $table = leafHost()->table('t', $columns, [['title' => 'a'], ['year' => 1999, 'title' => 'b', 'extra' => 'x'], ['title' => null, 'year' => true]]);

    expect($table->columns())->toBe($columns)
        ->and($table->rows())->toBe([['title' => 'a', 'year' => ''], ['title' => 'b', 'year' => '1999'], ['title' => '', 'year' => '1']])
        ->and($table->engineCells())->toBe([['a', ''], ['b', '1999'], ['', '1']])
        ->and(fn () => leafHost()->table('u', $columns, [['title' => ['nested']]]))->toThrow(WindowException::class, 'scalar or null')
        ->and(fn () => leafHost()->table('v', [new TableColumn('a', 'A'), new TableColumn('a', 'B')]))->toThrow(WindowException::class, 'unique')
        ->and(fn () => leafHost()->table('w', ['title']))->toThrow(WindowException::class, 'TableColumn');
});

it('selects table rows in range, keeps the selection through appends and clears it on replace', function (): void {
    $table = leafHost()->table('t', [new TableColumn('title', 'Title')], [['title' => 'a'], ['title' => 'b']]);
    $table->selectRow(1)->appendRow(['title' => 'c']);

    expect($table->selectedRow())->toBe(1)
        ->and($table->rows())->toHaveCount(3)
        ->and(fn () => $table->selectRow(3))->toThrow(WindowException::class, 'out of range')
        ->and(fn () => $table->selectRow(-1))->toThrow(WindowException::class, 'out of range');

    $table->setRows([['title' => 'z']]);
    expect($table->selectedRow())->toBeNull();
    $table->nativeRowSelected(0);
    expect($table->selectedRow())->toBe(0);
    $table->clearRows();

    expect($table->rows())->toBe([])
        ->and($table->selectedRow())->toBeNull()
        ->and(fn () => $table->selectRow(0))->toThrow(WindowException::class, 'out of range')
        ->and($table->log)->toBe(['selected:1', 'rows:3', 'selected:1', 'rows:1', 'selected:none', 'rows:0', 'selected:none']);
});

it('drives a video and reads position and duration from the engine', function (): void {
    $video = leafHost()->video('v', '/tmp/clip.mp4');
    $video->play()->seek(1.5)->setMuted(true)->setLoop(true)->pause()->setFile('/tmp/other.mp4');
    $video->position = 1.5;
    $video->duration = 4.0;

    expect($video->file())->toBe('/tmp/other.mp4')
        ->and($video->isPlaying())->toBeFalse()
        ->and($video->isMuted())->toBeTrue()
        ->and($video->isLooping())->toBeTrue()
        ->and($video->position())->toBe(1.5)
        ->and($video->duration())->toBe(4.0)
        ->and($video->log)->toBe(['play', 'seek:1.5', 'muted:1', 'loop:1', 'pause', 'file:/tmp/other.mp4'])
        ->and(fn () => $video->seek(-1.0))->toThrow(WindowException::class, '>= 0')
        ->and(fn () => $video->seek(NAN))->toThrow(WindowException::class, '>= 0')
        ->and(fn () => $video->seek(INF))->toThrow(WindowException::class, '>= 0');
    $video->nativeStateChanged(true);

    expect($video->isPlaying())->toBeTrue()
        ->and($video->log)->toHaveCount(6);
});

it('refuses every setter and native read once removed', function (Closure $create, Closure $call): void {
    $leaf = $create(leafHost());
    $leaf->remove();

    expect(fn () => $call($leaf))->toThrow(WindowException::class, 'was removed');
})->with([
    'label' => [fn ($h) => $h->label('x', 'x'), fn ($l) => $l->setWrap(true)],
    'button' => [fn ($h) => $h->button('x', 'x'), fn ($l) => $l->setLabel('y')],
    'image' => [fn ($h) => $h->image('x'), fn ($l) => $l->setFile('/a.png')],
    'spinner' => [fn ($h) => $h->spinner('x'), fn ($l) => $l->start()],
    'progress bar' => [fn ($h) => $h->progressBar('x'), fn ($l) => $l->setFraction(0.5)],
    'text input' => [fn ($h) => $h->textInput('x'), fn ($l) => $l->setPlaceholder('p')],
    'text area' => [fn ($h) => $h->textArea('x'), fn ($l) => $l->setValue('v')],
    'checkbox' => [fn ($h) => $h->checkbox('x', 'x'), fn ($l) => $l->setChecked(true)],
    'toggle' => [fn ($h) => $h->toggle('x'), fn ($l) => $l->setOn(true)],
    'toggle button' => [fn ($h) => $h->toggleButton('x', 'x'), fn ($l) => $l->setPressed(true)],
    'slider' => [fn ($h) => $h->slider('x', 0.0, 1.0, 0.5), fn ($l) => $l->setRange(0.0, 2.0)],
    'dropdown' => [fn ($h) => $h->dropdown('x', ['a']), fn ($l) => $l->select(0)],
    'datepicker' => [fn ($h) => $h->datepicker('x'), fn ($l) => $l->setDate(null)],
    'table' => [fn ($h) => $h->table('x', [new TableColumn('a', 'A')]), fn ($l) => $l->appendRow(['a' => 1])],
    'video play' => [fn ($h) => $h->video('x'), fn ($l) => $l->play()],
    'video position' => [fn ($h) => $h->video('x'), fn ($l) => $l->position()],
]);
