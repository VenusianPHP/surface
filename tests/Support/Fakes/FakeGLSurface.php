<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\Drawing\GLSurface;

/** A GLSurface that counts makeCurrent()/present() and appends to a shared order log. */
final class FakeGLSurface implements GLSurface
{
    public int $made_current = 0;

    public int $presented = 0;

    /** @var list<string> shared with the FakeExecutor so ordering is provable */
    public array $log = [];

    /** @var array{int, int} */
    public array $size = [0, 0];

    public function makeCurrent(): void
    {
        $this->made_current++;
        $this->log[] = 'makeCurrent';
    }

    public function present(): void
    {
        $this->presented++;
        $this->log[] = 'present';
    }

    public function drawableSize(): array
    {
        return $this->size;
    }
}
