<?php

namespace Surface\Contracts\Video;

/**
 * A source's sound, in order, as interleaved signed 16-bit PCM at the rate
 * and channels it was opened with. Used by one thread.
 */
interface SoundStream
{
    /**
     * Write the next frames of sound at $address, at most $frames of them (a
     * frame is one sample for every channel).
     *
     * @return array{float, int}|null the time of the first frame written, in seconds from the start, and how many were written (1 or more); null once the source has ended
     */
    public function read(int $address, int $frames): ?array;

    /** The next frames read are the source's at $seconds, to within one of its packets. */
    public function seek(float $seconds): void;

    public function close(): void;
}
