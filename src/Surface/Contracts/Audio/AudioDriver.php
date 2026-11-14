<?php

namespace Surface\Contracts\Audio;

/**
 * One audio library as a driver: the sound device it opened, and the streams
 * that play through it. Many streams mix into the one device.
 *
 * The device is opened once, by the thread that makes the driver. Another
 * thread reaches the same device with attach(share()): a PHP object does not
 * cross threads, the device's address does. A stream belongs to the thread
 * that opened it.
 */
interface AudioDriver
{
    /** The driver's config name: 'miniaudio'. */
    public function driver(): string;

    /** Frames a second the device plays; a stream opened at this rate is not resampled. */
    public function rate(): int;

    /** The channels the device plays. */
    public function channels(): int;

    /**
     * A stream of interleaved signed 16-bit PCM at $rate frames a second in
     * $channels channels, stopped and empty.
     *
     * @throws AudioException When the library refuses the format.
     */
    public function open(int $rate, int $channels): AudioStream;

    /** What attach() needs to reach this device from another thread: scalars only. */
    public function share(): array;

    /**
     * The device another thread opened, from its share(). It opens streams as
     * the owner does; close() on it closes nothing.
     */
    public static function attach(array $shared): static;

    /** The owner's closes the device; every stream is closed first, by whoever opened it. */
    public function close(): void;
}
