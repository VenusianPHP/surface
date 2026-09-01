<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * Moving pictures from a file path, with native controls.
 *
 * A path is the one loading story both engines share — bytes from the
 * network go through a temp file, exactly like OSImage. Remote stream
 * URLs wait until both engines can honestly take one.
 */
interface OSVideo extends OSView
{
    /** The file path Surface last loaded, or null while the player is empty. */
    public function path(): ?string;

    public function setPath(string $path): static;

    public function play(): static;

    public function pause(): static;

    public function isPlaying(): bool;

    public function setMuted(bool $muted): static;

    public function isMuted(): bool;
}
