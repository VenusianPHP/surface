<?php

it('the make:font stub is an empty GFXFont scaffold in house shape', function () {
    $stub = file_get_contents(dirname(__DIR__, 2).'/src/Surface/Fonts/Console/stubs/font.stub');

    expect($stub)->toContain('namespace {{ namespace }};')
        ->and($stub)->toContain('class {{ class }} extends GFXFont')
        ->and($stub)->toContain('use Surface\\Contracts\\Fonts\\GFXFont;')
        ->and($stub)->toContain('protected int $y_advance = 8;')
        ->and($stub)->toContain('protected array $bitmaps = [];')
        ->and($stub)->not->toContain('getClass');
});
