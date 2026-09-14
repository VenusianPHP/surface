<?php

namespace Surface\Contracts\Stage\Events;

use Voyager\Contracts\IOPools\Occurrence;

/** The stage's size or backing scale changed. Named stage.resized.<stage>; size in points. */
final class StageResized implements Occurrence
{
    public readonly string $name;

    public function __construct(
        public readonly string $stage,
        public readonly int $width,
        public readonly int $height,
        public readonly float $scale,
    ) {
        $this->name = "stage.resized.{$stage}";
    }
}
