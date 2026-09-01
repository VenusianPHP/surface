<?php

use Surface\NativeWindows\Views\Frame;

it('exposes position and size', function () {
    $frame = new Frame(10, 20, 300, 40);

    expect($frame->x)->toBe(10)
        ->and($frame->y)->toBe(20)
        ->and($frame->width)->toBe(300)
        ->and($frame->height)->toBe(40);
});

it('moves without touching size', function () {
    $frame = new Frame(10, 20, 300, 40);

    $moved = $frame->withPosition(50, 60);

    expect($moved)->not->toBe($frame)
        ->and($moved->x)->toBe(50)
        ->and($moved->y)->toBe(60)
        ->and($moved->width)->toBe(300)
        ->and($moved->height)->toBe(40)
        ->and($frame->x)->toBe(10);
});

it('resizes without touching position', function () {
    $frame = new Frame(10, 20, 300, 40);

    $sized = $frame->withSize(640, 480);

    expect($sized)->not->toBe($frame)
        ->and($sized->x)->toBe(10)
        ->and($sized->y)->toBe(20)
        ->and($sized->width)->toBe(640)
        ->and($sized->height)->toBe(480)
        ->and($frame->width)->toBe(300);
});
