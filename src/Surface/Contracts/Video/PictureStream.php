<?php

namespace Surface\Contracts\Video;

/**
 * A source's pictures, in order. One picture is held between next() and the
 * next next(); convert() writes the held one where it is told. Used by one
 * thread.
 */
interface PictureStream
{
    /**
     * Decode the next picture and hold it.
     *
     * @return float|null its time in seconds from the start; null once the source has ended
     */
    public function next(): ?float;

    /**
     * The held picture as straight RGBA8, the VideoInfo's width × height, top
     * row first, no padding, written at $address.
     *
     * @throws VideoException When the driver has no conversion from the picture's format.
     */
    public function convert(int $address): void;

    /**
     * The next picture decoded is the latest the source can start from at or
     * before $seconds (a key frame): the pictures after it lead up to that time.
     */
    public function seek(float $seconds): void;

    public function close(): void;
}
