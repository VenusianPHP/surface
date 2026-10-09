<?php

declare(strict_types=1);

use Surface\HumanInput\InputFrame;

it('counts only the first read after a frame ends, and catches up on it', function (): void {
    $catch_ups = 0;
    $frame = new InputFrame(fn (): int => 1_000, started_ns: 0);
    $frame->catchUpWith(function () use (&$catch_ups): void {
        $catch_ups++;
    });

    $frame->read();
    $frame->read();

    expect($catch_ups)->toBe(1)
        ->and($frame->spent(1))->toBeTrue();

    $frame->read();

    expect($catch_ups)->toBe(2);
});

it('ignores reads made while input is applied, the catch-up included', function (): void {
    $frame = new InputFrame(fn (): int => 1_000, started_ns: 0);
    $frame->applying(fn () => $frame->read());

    expect($frame->spent(1))->toBeFalse();

    $inner = 0;
    $frame->catchUpWith(function () use ($frame, &$inner): void {
        $inner++;
        $frame->read();
    });
    $frame->read();

    expect($inner)->toBe(1);
});

it('ends a frame that was read, and one no read touched for two frame lengths', function (): void {
    $frame = new InputFrame(fn (): int => 10, started_ns: 1_000);

    expect($frame->spent(1_019))->toBeFalse()
        ->and($frame->spent(1_020))->toBeTrue();

    $frame->read();

    expect($frame->spent(1_021))->toBeTrue()
        ->and($frame->spent(1_040))->toBeFalse()
        ->and($frame->spent(1_041))->toBeTrue();
});

it('asks the frame length each time, so a changed refresh rate applies at once', function (): void {
    $length = 10;
    $frame = new InputFrame(function () use (&$length): int {
        return $length;
    }, started_ns: 0);

    expect($frame->spent(20))->toBeTrue();

    $length = 100;

    expect($frame->spent(20))->toBeFalse();
});

it('throws from every read until a catch-up finishes, then counts the read', function (): void {
    $frame = new InputFrame(fn (): int => 1_000, started_ns: 0);
    $ok = false;
    $frame->catchUpWith(function () use (&$ok): void {
        if (! $ok) {
            throw new RuntimeException('no engine');
        }
    });

    expect(fn () => $frame->read())->toThrow(RuntimeException::class)
        ->and(fn () => $frame->read())->toThrow(RuntimeException::class)
        ->and($frame->spent(1))->toBeFalse();

    $ok = true;
    $frame->read();

    expect($frame->spent(1))->toBeTrue();
});
