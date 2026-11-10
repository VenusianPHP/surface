<?php

declare(strict_types=1);

use Surface\Contracts\Windows\Mail\View\GroupClicked;
use Surface\Windows\Primitives\TKPrimitiveGroup;
use Venusian\Surface\Tests\Fixtures\FakeHost;

it('is not clickable until it is set so', function (): void {
    $card = (new FakeHost('main'))->column('m')->column('card');

    expect($card->isClickable())->toBeFalse()
        ->and($card->setClickable()->isClickable())->toBeTrue()
        ->and($card->setClickable(false)->isClickable())->toBeFalse();
});

it('clicks the nearest clickable group through passive content', function (): void {
    $card = (new FakeHost('main'))->column('m')->column('card')->setClickable();
    $row = $card->row('names');
    $title = $row->label('title', 'Stargazer');
    $icon = $row->image('icon');

    expect(TKPrimitiveGroup::clickTarget($title))->toBe($card)
        ->and(TKPrimitiveGroup::clickTarget($icon))->toBe($card)
        ->and(TKPrimitiveGroup::clickTarget($row))->toBe($card)
        ->and(TKPrimitiveGroup::clickTarget($card))->toBe($card);
});

it('takes the innermost clickable group when groups nest', function (): void {
    $outer = (new FakeHost('main'))->column('m')->column('outer')->setClickable();
    $inner = $outer->column('inner')->setClickable();

    expect(TKPrimitiveGroup::clickTarget($inner->label('a', 'A')))->toBe($inner);
});

it('leaves a control its own click, and clicks nothing outside a clickable group', function (): void {
    $m = (new FakeHost('main'))->column('m');
    $card = $m->column('card')->setClickable();

    expect(TKPrimitiveGroup::clickTarget($card->button('go', 'Go')))->toBeNull()
        ->and(TKPrimitiveGroup::clickTarget($m->label('plain', 'Plain')))->toBeNull();
});

it('clicks nothing while the group or one of its groups is disabled', function (): void {
    $m = (new FakeHost('main'))->column('m');
    $card = $m->column('card')->setClickable();
    $title = $card->label('title', 'T');

    $card->disable();
    expect(TKPrimitiveGroup::clickTarget($title))->toBeNull();

    $card->enable();
    $m->disable();
    expect(TKPrimitiveGroup::clickTarget($title))->toBeNull();
});

it('names its mail view.group-clicked', function (): void {
    $mail = new GroupClicked('main', 'm.card', 'u1', 3.0, 4.5);

    expect($mail->name())->toBe('view.group-clicked.main.m.card')
        ->and([$mail->window(), $mail->path(), $mail->uuid(), $mail->x, $mail->y])->toBe(['main', 'm.card', 'u1', 3.0, 4.5]);
});
