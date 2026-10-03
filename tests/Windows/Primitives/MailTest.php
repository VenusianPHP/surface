<?php

declare(strict_types=1);

use Surface\Contracts\Windows\Mail\View\ButtonClicked;
use Surface\Contracts\Windows\Mail\View\DateChanged;
use Surface\Contracts\Windows\Mail\View\PrimitiveMail;
use Surface\Contracts\Windows\Mail\View\RowSelected;
use Surface\Contracts\Windows\Mail\View\SelectionChanged;
use Surface\Contracts\Windows\Mail\View\TextChanged;
use Surface\Contracts\Windows\Mail\View\TextSubmitted;
use Surface\Contracts\Windows\Mail\View\Toggled;
use Surface\Contracts\Windows\Mail\View\ValueChanged;
use Surface\Contracts\Windows\Mail\View\VideoEnded;
use Surface\Contracts\Windows\Mail\View\VideoFailed;
use Surface\Contracts\Windows\Mail\View\VideoPaused;
use Surface\Contracts\Windows\Mail\View\VideoPlaying;
use Surface\Contracts\Windows\Mail\View\ViewResized;
use Surface\Contracts\Windows\Mail\WindowMail;
use Surface\Contracts\Windows\Mail\WindowResized;
use Voyager\Contracts\Signals\NamedSignal;

it('names each primitive mail view.<event>.<window>.<path>', function (PrimitiveMail $mail, string $name): void {
    expect($mail)->toBeInstanceOf(NamedSignal::class)
        ->and($mail->name())->toBe($name)
        ->and($mail->window())->toBe('main')
        ->and($mail->path())->toBe('main.toolbar.save')
        ->and($mail->uuid())->toBe('u1');
})->with([
    'clicked' => [new ButtonClicked('main', 'main.toolbar.save', 'u1'), 'view.clicked.main.main.toolbar.save'],
    'text changed' => [new TextChanged('main', 'main.toolbar.save', 'u1', 'ab'), 'view.text-changed.main.main.toolbar.save'],
    'text submitted' => [new TextSubmitted('main', 'main.toolbar.save', 'u1', 'ab'), 'view.text-submitted.main.main.toolbar.save'],
    'toggled' => [new Toggled('main', 'main.toolbar.save', 'u1', true), 'view.toggled.main.main.toolbar.save'],
    'value changed' => [new ValueChanged('main', 'main.toolbar.save', 'u1', 0.5), 'view.value-changed.main.main.toolbar.save'],
    'selection changed' => [new SelectionChanged('main', 'main.toolbar.save', 'u1', 1, 'b'), 'view.selection-changed.main.main.toolbar.save'],
    'date changed' => [new DateChanged('main', 'main.toolbar.save', 'u1', new DateTimeImmutable('2026-10-02')), 'view.date-changed.main.main.toolbar.save'],
    'row selected' => [new RowSelected('main', 'main.toolbar.save', 'u1', 0, ['title' => 'a']), 'view.row-selected.main.main.toolbar.save'],
    'resized' => [new ViewResized('main', 'main.toolbar.save', 'u1', 10, 20), 'view.resized.main.main.toolbar.save'],
    'video playing' => [new VideoPlaying('main', 'main.toolbar.save', 'u1'), 'view.video-playing.main.main.toolbar.save'],
    'video paused' => [new VideoPaused('main', 'main.toolbar.save', 'u1'), 'view.video-paused.main.main.toolbar.save'],
    'video ended' => [new VideoEnded('main', 'main.toolbar.save', 'u1'), 'view.video-ended.main.main.toolbar.save'],
    'video failed' => [new VideoFailed('main', 'main.toolbar.save', 'u1', 'no such file'), 'view.video-failed.main.main.toolbar.save'],
]);

it('names the window resize window.resized.<window> and carries the size', function (): void {
    $resized = new WindowResized('main', 640, 400);

    expect($resized)->toBeInstanceOf(WindowMail::class)
        ->and($resized->name())->toBe('window.resized.main')
        ->and([$resized->width, $resized->height])->toBe([640, 400]);
});

it('carries each payload as given', function (): void {
    $date = new DateTimeImmutable('2026-10-02');
    $cleared = new RowSelected('main', 't', 'u', null, null);

    expect($cleared->row)->toBeNull()
        ->and($cleared->cells)->toBeNull()
        ->and((new DateChanged('main', 'd', 'u', $date))->date)->toBe($date)
        ->and((new SelectionChanged('main', 'd', 'u', -1, null))->option)->toBeNull()
        ->and((new Toggled('main', 'c', 'u', false))->on)->toBeFalse()
        ->and((new ValueChanged('main', 's', 'u', 2.5))->value)->toBe(2.5)
        ->and((new TextSubmitted('main', 'i', 'u', 'x'))->value)->toBe('x')
        ->and([(new ViewResized('main', 'v', 'u', 3, 4))->width, (new ViewResized('main', 'v', 'u', 3, 4))->height])->toBe([3, 4])
        ->and((new VideoFailed('main', 'v', 'u', 'codec'))->reason)->toBe('codec');
});
