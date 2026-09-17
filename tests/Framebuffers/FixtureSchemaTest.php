<?php

use Venusian\Surface\Tests\Support\Framebuffers\FixtureRunner;

it('every fixture parses, names a kind, and has hex of even length', function (string $file) {
    $fx = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

    expect($fx)->toHaveKeys(['name', 'kind', 'width', 'height', 'format', 'steps'])
        ->and($fx['kind'])->toBeIn(['full', 'dirty', 'epaper', 'paged', 'ring']);
    FixtureRunner::spec($fx['format']);
    foreach ($fx['steps'] as $step) {
        foreach (['bytes_hex', 'rgba8_hex'] as $key) {
            if (isset($step['expect'][$key])) {
                expect(strlen($step['expect'][$key]) % 2)->toBe(0)
                    ->and(ctype_xdigit($step['expect'][$key]))->toBeTrue();
            }
        }
    }
})->with(fn () => FixtureRunner::files());

it('ships twenty-seven fixtures', function () {
    expect(FixtureRunner::files())->toHaveCount(27);
});
