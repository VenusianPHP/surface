<?php

namespace Surface\Contracts\Video;

/**
 * One decoding library as a video driver: it reads what a source is, and
 * opens the source's pictures and its sound as two streams, each decoded a
 * step at a time by whoever holds it. A driver plays nothing and keeps no
 * clock: VideoPlayer runs the streams on worker threads, shows the picture
 * that is due and feeds the sound to an AudioDriver.
 *
 * A driver is made again inside each worker thread from its class name alone,
 * so it is constructed with no arguments and holds nothing between calls. The
 * two streams of one source never share an object: each opens the source
 * itself and lives on its own thread.
 */
interface VideoDecoder
{
    /** The driver's config name: 'ffmpeg'. */
    public function driver(): string;

    /**
     * @throws VideoException When the source cannot be opened, or has no picture this driver decodes.
     */
    public function probe(string $source): VideoInfo;

    /**
     * @throws VideoException As probe().
     */
    public function pictures(string $source): PictureStream;

    /**
     * The source's sound as interleaved signed 16-bit PCM at $rate frames a
     * second in $channels channels, whatever the source holds.
     *
     * @throws VideoException When the source cannot be opened, or has no sound this driver decodes.
     */
    public function sound(string $source, int $rate, int $channels): SoundStream;
}
