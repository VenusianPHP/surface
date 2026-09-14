<?php

namespace Surface\Contracts\Stage\Events;

use Voyager\Contracts\IOPools\Occurrence;

/** The user asked the host to close this stage, or the stage was closed. Named stage.closed.<stage>. */
final class StageClosed implements Occurrence
{
    public readonly string $name;

    public function __construct(public readonly string $stage)
    {
        $this->name = "stage.closed.{$stage}";
    }
}
