<?php

namespace Surface\Contracts\Framebuffers;

interface Framebuffer
{
    public function viewportWidth(): int;

    public function viewportHeight(): int;

    public function hostFormat(): FormatSpec;

    public function getPixel(int $x, int $y): int;

    public function setPixel(int $x, int $y, int $value): static;

    /**
     * @param  array<int, array{0: int, 1: int, 2: int}>  $pixels  Each entry is [x, y, color]
     */
    public function setPixels(array $pixels): static;

    /**
     * @param  array<int, array{0: int, 1: int}>  $coordinates
     */
    public function setRegion(array $coordinates, int $value): static;

    public function setSegment(int $x, int $y, int $width, int $height, int $color): static;

    public function clear(): static;

    public function fill(int $color): static;

    public function blitTo(Framebuffer $target, int $offset_x = 0, int $offset_y = 0): Framebuffer;

    public function blitFrom(Framebuffer $source, int $offset_x = 0, int $offset_y = 0): Framebuffer;

    /**
     * Raw host bytes (optional layer).
     */
    public function dump(?int $layer = null): string;

    /**
     * Emit pixels in the requested FormatSpec.
     *
     * @return string|array<int, mixed>
     */
    public function flush(FormatSpec $spec, bool $as_array = false): string|array;

    /**
     * Bytes of one sub-rect in the requested FormatSpec. $region must already be
     * snapped to damageGranularity(); a region that is not is a caller bug.
     *
     * @return string|list<int>
     */
    public function flushRegion(Region $region, FormatSpec $spec, bool $as_array = false): string|array;

    /** RGBA8 bytes, top-left first, viewport size. */
    public function toRgba8(): string;

    /** Native address of the bytes; 0 when PHP owns them. */
    public function pointer(): int;

    public function damageGranularity(): DamageGranularity;

    /**
     * True when the logical canvas still holds the previous frame after a present.
     */
    public function preservesContentsOnPresent(): bool;
}