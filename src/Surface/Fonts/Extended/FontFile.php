<?php

namespace Surface\Fonts\Extended;

/** A font on disk: the file and the face inside it (collections hold more than one). */
final readonly class FontFile
{
    public function __construct(
        public string $path,
        public int $index = 0,
    ) {}
}
