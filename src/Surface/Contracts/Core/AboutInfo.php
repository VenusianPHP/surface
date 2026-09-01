<?php

namespace Surface\Contracts\Core;

/**
 * What the OS's own About panel shows. Program-level identity, registered
 * once on the shuttle; the ABOUT menu role reads it on either engine.
 *
 * Credits is honoured on GTK (comments) and skipped on AppKit, whose
 * Credits key wants an attributed string — measured later, not promised.
 */
final class AboutInfo
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $version = null,
        public readonly ?string $copyright = null,
        public readonly ?string $credits = null,
        public readonly ?string $website = null,
    ) {}
}
