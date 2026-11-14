<?php

namespace Surface\Windows\Video;

use AVCodecContext;
use AVFormatContext;
use AVFrame;
use AVPacket;
use Surface\Contracts\Windows\WindowException;
use SwsContext;

/**
 * One file's picture through ext-ffmpeg: its best video stream demuxed and
 * decoded a frame at a time, each converted to RGBA8 at an address it is
 * given. One frame is held between next() and the next next(); nothing is
 * copied through PHP. Every FFmpeg object is freed by close().
 */
final class FfmpegStream
{
    public readonly int $width;

    public readonly int $height;

    /** Frames a second, as the stream says; 0.0 when it does not. */
    public readonly float $frameRate;

    /** Seconds; null when neither the stream nor the file says. */
    public readonly ?float $duration;

    private ?AVFormatContext $format = null;

    private ?AVCodecContext $decoder = null;

    private ?AVPacket $packet = null;

    private ?AVFrame $frame = null;

    private ?SwsContext $scaler = null;

    /** @var array{int, int, int}|null the pixel format, width and height the scaler was made for */
    private ?array $scaled = null;

    private int $index = -1;

    /** Seconds in one tick of the stream's timestamps. */
    private float $unit = 0.0;

    /** The stream's first timestamp: second 0. */
    private int $origin = 0;

    /** The file has ended and the decoder was told: it gives what it still holds, then nothing. */
    private bool $draining = false;

    /** The time of the frame last decoded. */
    private ?float $last = null;

    /**
     * @throws WindowException With FFmpeg's reason, when the file cannot be opened or holds no picture it decodes.
     */
    public function __construct(string $file)
    {
        $format = null;
        $options = null;
        $status = avformat_open_input($format, $file, null, $options);
        if ($status < 0 || is_null($format)) {
            throw new WindowException("Video file '{$file}' cannot be opened: ".self::reason($status).'.');
        }
        $this->format = $format;

        try {
            $status = avformat_find_stream_info($format, null);
            if ($status < 0) {
                throw new WindowException("Video file '{$file}' cannot be read: ".self::reason($status).'.');
            }
            $codec = null;
            $this->index = av_find_best_stream($format, AVMEDIA_TYPE_VIDEO, -1, -1, $codec, 0);
            if ($this->index < 0 || is_null($codec)) {
                throw new WindowException("Video file '{$file}' has no picture FFmpeg decodes.");
            }
            $stream = $format->streams()[$this->index];
            $this->decoder = avcodec_alloc_context3($codec)
                ?? throw new WindowException("Video file '{$file}': FFmpeg made no decoder.");
            $status = avcodec_parameters_to_context($this->decoder, $stream->codecpar());
            $options = null;
            $status = $status < 0 ? $status : avcodec_open2($this->decoder, $codec, $options);
            if ($status < 0) {
                throw new WindowException("Video file '{$file}': its {$codec->name()} decoder did not open: ".self::reason($status).'.');
            }
            $this->width = $this->decoder->width();
            $this->height = $this->decoder->height();
            if ($this->width < 1 || $this->height < 1) {
                throw new WindowException("Video file '{$file}' does not say its picture's size.");
            }

            $this->unit = av_q2d($stream->time_base());
            $this->origin = $stream->start_time() === AV_NOPTS_VALUE ? 0 : $stream->start_time();
            $rate = $stream->avg_frame_rate();
            $this->frameRate = $rate->num > 0 && $rate->den > 0 ? $rate->num / $rate->den : 0.0;
            $this->duration = match (true) {
                $stream->duration() !== AV_NOPTS_VALUE && $stream->duration() > 0 => $stream->duration() * $this->unit,
                $format->duration() !== AV_NOPTS_VALUE && $format->duration() > 0 => $format->duration() / AV_TIME_BASE,
                default => null,
            };
            $this->packet = av_packet_alloc();
            $this->frame = av_frame_alloc();
        } catch (WindowException $refused) {
            $this->close();

            throw $refused;
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Decode the next frame and hold it.
     *
     * @return float|null its time in seconds from the start; null once the stream has ended
     */
    public function next(): ?float
    {
        if (is_null($this->decoder)) {
            return null;
        }
        while (true) {
            if (avcodec_receive_frame($this->decoder, $this->frame) >= 0) {
                $stamp = $this->frame->best_effort_timestamp();
                $step = $this->frameRate > 0.0 ? 1.0 / $this->frameRate : 0.0;

                return $this->last = $stamp === AV_NOPTS_VALUE
                    ? (is_null($this->last) ? 0.0 : $this->last + $step)
                    : ($stamp - $this->origin) * $this->unit;
            }
            if ($this->draining) {
                return null;
            }
            // The decoder wants input: the stream's next packet, or the word that the file has ended.
            if (av_read_frame($this->format, $this->packet) < 0) {
                avcodec_send_packet($this->decoder, null);
                $this->draining = true;

                continue;
            }
            if ($this->packet->stream_index() === $this->index) {
                avcodec_send_packet($this->decoder, $this->packet);
            }
            av_packet_unref($this->packet);
        }
    }

    /**
     * The frame next() holds, as $width × $height RGBA8 pixels, top row first,
     * no padding, written at $address.
     *
     * @throws WindowException When FFmpeg has no conversion from the frame's pixel format.
     */
    public function convert(int $address): void
    {
        $from = [$this->frame->format(), $this->frame->width(), $this->frame->height()];
        if ($from !== $this->scaled) {
            sws_freeContext($this->scaler);
            $this->scaler = sws_getContext($from[1], $from[2], $from[0], $this->width, $this->height, AV_PIX_FMT_RGBA, SWS_BILINEAR, null, null, null)
                ?? throw new WindowException('FFmpeg converts no '.(av_get_pix_fmt_name($from[0]) ?? 'unknown')." frame of {$from[1]} x {$from[2]} to RGBA.");
            $this->scaled = $from;
        }
        sws_scale($this->scaler, $this->frame->data(), $this->frame->linesize(), 0, $from[2], [$address], [$this->width * 4]);
    }

    /** The next frame decoded is the key frame at or before $seconds: the ones after it lead up to that time. */
    public function seek(float $seconds): void
    {
        if (is_null($this->decoder)) {
            return;
        }
        av_seek_frame($this->format, $this->index, $this->origin + (int) floor($seconds / $this->unit), AVSEEK_FLAG_BACKWARD);
        avcodec_flush_buffers($this->decoder);
        $this->draining = false;
        $this->last = null;
    }

    public function close(): void
    {
        sws_freeContext($this->scaler);
        $this->scaler = null;
        $this->scaled = null;
        av_frame_free($this->frame);
        av_packet_free($this->packet);
        avcodec_free_context($this->decoder);
        avformat_close_input($this->format);
    }

    private static function reason(int $status): string
    {
        $text = null;
        av_strerror($status, $text, 256);

        return $text ?: "FFmpeg error {$status}";
    }
}
