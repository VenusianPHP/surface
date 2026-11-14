<?php

namespace Surface\Video\Ffmpeg;

use Surface\Contracts\Video\PictureStream;
use Surface\Contracts\Video\VideoException;
use SwsContext;

/** A source's best video stream, each frame converted to RGBA8 by libswscale at the address it is given. */
final class FfmpegPictures extends FfmpegSource implements PictureStream
{
    private readonly int $width;

    private readonly int $height;

    /** Seconds a picture lasts, by the stream's rate; 0.0 when it has none. */
    private readonly float $step;

    private ?SwsContext $scaler = null;

    /** @var array{int, int, int}|null the pixel format, width and height the scaler was made for */
    private ?array $scaled = null;

    /** The time of the picture last decoded. */
    private ?float $last = null;

    /**
     * @throws VideoException With FFmpeg's reason.
     */
    public function __construct(string $source)
    {
        $stream = $this->open($source, AVMEDIA_TYPE_VIDEO);
        $this->width = $this->decoder->width();
        $this->height = $this->decoder->height();
        $rate = $stream->avg_frame_rate();
        $this->step = $rate->num > 0 && $rate->den > 0 ? $rate->den / $rate->num : 0.0;
        if ($this->width < 1 || $this->height < 1) {
            $this->close();

            throw VideoException::unreadable($source, "it does not say its picture's size");
        }
    }

    public function next(): ?float
    {
        if (! $this->receive()) {
            return null;
        }

        // A picture with no stamp follows the last by the stream's rate.
        return $this->last = $this->time() ?? (is_null($this->last) ? 0.0 : $this->last + $this->step);
    }

    public function convert(int $address): void
    {
        $from = [$this->frame->format(), $this->frame->width(), $this->frame->height()];
        if ($from !== $this->scaled) {
            sws_freeContext($this->scaler);
            $this->scaler = sws_getContext($from[1], $from[2], $from[0], $this->width, $this->height, AV_PIX_FMT_RGBA, SWS_BILINEAR, null, null, null)
                ?? throw new VideoException('FFmpeg converts no '.(av_get_pix_fmt_name($from[0]) ?? 'unknown')." picture of {$from[1]} x {$from[2]} to RGBA.");
            $this->scaled = $from;
        }
        sws_scale($this->scaler, $this->frame->data(), $this->frame->linesize(), 0, $from[2], [$address], [$this->width * 4]);
    }

    public function seek(float $seconds): void
    {
        $this->rewind($seconds);
        $this->last = null;
    }

    public function close(): void
    {
        sws_freeContext($this->scaler);
        $this->scaler = null;
        $this->scaled = null;
        parent::close();
    }
}
