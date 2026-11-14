<?php

namespace Surface\Contracts\Audio;

/**
 * Sound on its way to the device: a queue of interleaved signed 16-bit PCM
 * that one writer fills and the device empties while the stream is started.
 * Stopped, it keeps what it holds and plays silence. Empty and started, it
 * plays silence until more is written.
 *
 * The writer asks where to put frames (acquire()), has them written there,
 * and says how many it wrote (commit()): nothing is copied through PHP.
 */
interface AudioStream
{
    public function rate(): int;

    public function channels(): int;

    /**
     * Room for the next frames.
     *
     * @return array{int, int} the address to write at and how many frames fit there: at most $frames, 0 when the queue is full
     */
    public function acquire(int $frames): array;

    /** $frames, written at the address acquire() last gave, join the queue. */
    public function commit(int $frames): void;

    /** Frames committed that the device has not taken yet. */
    public function queued(): int;

    public function start(): void;

    public function stop(): void;

    /** Drops what is queued. The stream is stopped first, and stays stopped. */
    public function clear(): void;

    /** 0.0 is silent, 1.0 is the sound as written. */
    public function setVolume(float $volume): void;

    public function close(): void;
}
