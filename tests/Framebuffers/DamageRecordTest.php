<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\DamageGranularity;
use Surface\Contracts\Framebuffers\Region;
use Surface\Framebuffers\DamageRecord;

/** @return list<array{int, int, int, int}> */
function boxes(array $regions): array
{
    return array_map(fn (Region $r): array => [$r->x, $r->y, $r->width, $r->height], $regions);
}

it('merges rects that touch, edge or corner, and keeps apart the ones that do not', function (): void {
    $record = new DamageRecord();
    $record->add(new Region(1, 1, 1, 1));
    $record->add(new Region(2, 2, 1, 1));     // corner to corner
    $record->add(new Region(6, 6, 1, 1));

    expect(boxes($record->written()))->toBe([[1, 1, 2, 2], [6, 6, 1, 1]]);

    $record->add(new Region(3, 3, 3, 3));     // bridges both

    expect(boxes($record->written()))->toBe([[1, 1, 6, 6]]);
});

it('collapses to one bounding box past sixteen rects, and stays one', function (): void {
    $record = new DamageRecord();
    for ($i = 0; $i < 16; $i++) {
        $record->add(new Region(($i % 4) * 4, intdiv($i, 4) * 4, 1, 1));
    }

    expect($record->written())->toHaveCount(16);

    $record->add(new Region(2, 2, 1, 1));

    expect(boxes($record->written()))->toBe([[0, 0, 13, 13]]);

    $record->add(new Region(20, 0, 1, 1));

    expect(boxes($record->written()))->toBe([[0, 0, 21, 13]]);
});

it('snaps to a granularity and merges what snapping made touch', function (): void {
    $record = new DamageRecord();
    $record->add(new Region(3, 1, 1, 1));
    $record->add(new Region(5, 9, 1, 1));

    expect(boxes($record->regions(DamageGranularity::pixel(8, 16))))->toBe([[3, 1, 1, 1], [5, 9, 1, 1]])
        ->and(boxes($record->regions(DamageGranularity::rows(8, 8, 16))))->toBe([[0, 0, 8, 16]]);
});

it('starts empty and clears back to empty', function (): void {
    $record = new DamageRecord();
    $record->add(new Region(0, 0, 4, 4));
    $record->clear();

    expect($record->written())->toBe([])
        ->and($record->regions(DamageGranularity::pixel(8, 8)))->toBe([]);
});
