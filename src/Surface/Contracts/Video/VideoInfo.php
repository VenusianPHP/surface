<?php

namespace Surface\Contracts\Video;

/** What a source says of itself before a frame is decoded. */
final readonly class VideoInfo
{
    /**
     * @param  int  $width  the picture's, in pixels
     * @param  int  $height  the picture's, in pixels
     * @param  float  $frameRate  pictures a second; 0.0 when the source does not say
     * @param  float|null  $duration  seconds; null when the source does not say
     * @param  bool  $hasSound  whether it carries sound this driver decodes
     */
    public function __construct(
        public int $width,
        public int $height,
        public float $frameRate,
        public ?float $duration,
        public bool $hasSound,
    ) {}
}
