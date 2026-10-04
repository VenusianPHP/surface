<?php

use Surface\Contracts\Drawing\Output;
use Surface\Contracts\Windows\Primitives\TKCanvas;

it('makes a toolkit canvas a drawing output', function () {
    expect(is_subclass_of(TKCanvas::class, Output::class))->toBeTrue();
});
