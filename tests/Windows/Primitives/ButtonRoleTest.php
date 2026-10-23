<?php

declare(strict_types=1);

use Surface\Contracts\Windows\Primitives\ButtonRole;
use Surface\Windows\Primitives\TKButton;
use Venusian\Surface\Tests\Fixtures\FakeHost;

it('gives a button a role and finds it as the holder', function (): void {
    $host = new FakeHost('main');
    $ok = $host->column('m')->button('ok', 'OK');

    expect($ok->setRole(ButtonRole::DEFAULT))->toBe($ok)
        ->and($ok->role())->toBe(ButtonRole::DEFAULT)
        ->and(TKButton::holder($host, ButtonRole::DEFAULT))->toBe($ok)
        ->and(TKButton::holder($host, ButtonRole::CANCEL))->toBeNull();
});

it('moves a role inside one window only', function (): void {
    $one = new FakeHost('one');
    $two = new FakeHost('two');
    $column = $one->column('m');
    $a = $column->button('a', 'A')->setRole(ButtonRole::DEFAULT);
    $c = $two->column('m')->button('c', 'C')->setRole(ButtonRole::DEFAULT);
    $b = $column->button('b', 'B')->setRole(ButtonRole::DEFAULT);

    expect($a->role())->toBeNull()
        ->and($b->role())->toBe(ButtonRole::DEFAULT)
        ->and($c->role())->toBe(ButtonRole::DEFAULT)
        ->and(TKButton::holder($one, ButtonRole::DEFAULT))->toBe($b)
        ->and(TKButton::holder($two, ButtonRole::DEFAULT))->toBe($c);
});

it('gives a button one role at a time', function (): void {
    $host = new FakeHost('main');
    $ok = $host->column('m')->button('ok', 'OK')->setRole(ButtonRole::DEFAULT)->setRole(ButtonRole::CANCEL);

    expect($ok->role())->toBe(ButtonRole::CANCEL)
        ->and(TKButton::holder($host, ButtonRole::DEFAULT))->toBeNull()
        ->and(TKButton::holder($host, ButtonRole::CANCEL))->toBe($ok);
});

it('removes a role with null', function (): void {
    $host = new FakeHost('main');
    $ok = $host->column('m')->button('ok', 'OK')->setRole(ButtonRole::DEFAULT)->setRole(null);

    expect($ok->role())->toBeNull()
        ->and(TKButton::holder($host, ButtonRole::DEFAULT))->toBeNull();
});

it('drops the role of a removed holder', function (): void {
    $host = new FakeHost('main');
    $ok = $host->column('m')->button('ok', 'OK')->setRole(ButtonRole::DEFAULT);
    $ok->remove();

    expect(TKButton::holder($host, ButtonRole::DEFAULT))->toBeNull()
        ->and($ok->role())->toBeNull();
});

it('applies the look to the previous holder and the new holder', function (): void {
    $column = (new FakeHost('main'))->column('m');
    $a = $column->button('a', 'A')->setRole(ButtonRole::DEFAULT);
    $b = $column->button('b', 'B')->setRole(ButtonRole::DEFAULT);
    $b->setRole(ButtonRole::DEFAULT);

    expect($a->log)->toBe(['role:default', 'role:none'])
        ->and($b->log)->toBe(['role:default']);
});
