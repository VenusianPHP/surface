<?php

namespace Surface\Video\Ffmpeg;

use AVCodecContext;
use AVFormatContext;
use AVFrame;
use AVPacket;
use AVStream;
use Surface\Contracts\Video\VideoException;

/**
 * One stream of one source through ext-ffmpeg: the source opened, its best
 * stream of a media type found, that stream's decoder opened, and its frames
 * decoded one at a time into the one AVFrame this object holds. Times are
 * seconds from the source's own start, the same line for every stream of it.
 * Every FFmpeg object is freed by close().
 */
abstract class FfmpegSource
{
    protected ?AVFormatContext $format = null;

    protected ?AVCodecContext $decoder = null;

    protected ?AVFrame $frame = null;

    private ?AVPacket $packet = null;

    private int $index = -1;

    /** Seconds in one tick of the stream's timestamps. */
    private float $unit = 0.0;

    /** The source's start, in seconds: second 0. */
    private float $origin = 0.0;

    /** The source has ended and the decoder was told: it gives what it still holds, then nothing. */
    private bool $draining = false;

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @param  int  $type  AVMEDIA_TYPE_VIDEO or AVMEDIA_TYPE_AUDIO
     * @return AVStream the stream opened
     *
     * @throws VideoException With FFmpeg's reason.
     */
    protected function open(string $source, int $type): AVStream
    {
        [$this->format, $this->index, $codec] = self::find($source, $type);
        $stream = $this->format->streams()[$this->index];
        $this->decoder = avcodec_alloc_context3($codec);
        $status = is_null($this->decoder) ? AVERROR(12) : avcodec_parameters_to_context($this->decoder, $stream->codecpar());
        if ($status >= 0) {
            // FFmpeg's own decoder threads, as many as it picks for this machine.
            $options = null;
            av_dict_set($options, 'threads', 'auto', 0);
            $status = avcodec_open2($this->decoder, $codec, $options);
            av_dict_free($options);
        }
        if ($status < 0) {
            $this->close();

            throw VideoException::unreadable($source, "its {$codec->name()} decoder did not open: ".self::reason($status));
        }
        $this->unit = av_q2d($stream->time_base());
        $this->origin = $this->format->start_time() === AV_NOPTS_VALUE ? 0.0 : $this->format->start_time() / AV_TIME_BASE;
        $this->packet = av_packet_alloc();
        $this->frame = av_frame_alloc();

        return $stream;
    }

    /**
     * The source opened and its best stream of $type, with a decoder for it.
     *
     * @return array{AVFormatContext, int, \AVCodec}
     *
     * @throws VideoException When the source does not open; noPicture() when it has no stream of $type FFmpeg decodes.
     */
    public static function find(string $source, int $type): array
    {
        $format = null;
        $options = null;
        $status = avformat_open_input($format, $source, null, $options);
        if ($status < 0 || is_null($format)) {
            throw VideoException::unreadable($source, self::reason($status));
        }
        $status = avformat_find_stream_info($format, null);
        if ($status < 0) {
            avformat_close_input($format);

            throw VideoException::unreadable($source, self::reason($status));
        }
        $codec = null;
        $index = av_find_best_stream($format, $type, -1, -1, $codec, 0);
        if ($index < 0 || is_null($codec)) {
            avformat_close_input($format);

            throw $type === AVMEDIA_TYPE_VIDEO ? VideoException::noPicture($source) : VideoException::unreadable($source, 'it has no sound FFmpeg decodes');
        }

        return [$format, $index, $codec];
    }

    /** Decode the stream's next frame into $this->frame. False once the source has ended. */
    protected function receive(): bool
    {
        if (is_null($this->decoder)) {
            return false;
        }
        while (true) {
            if (avcodec_receive_frame($this->decoder, $this->frame) >= 0) {
                return true;
            }
            if ($this->draining) {
                return false;
            }
            // The decoder wants input: the stream's next packet, or the word that the source has ended.
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

    /** The held frame's time; null when the stream does not stamp it. */
    protected function time(): ?float
    {
        $stamp = $this->frame->best_effort_timestamp();

        return $stamp === AV_NOPTS_VALUE ? null : $stamp * $this->unit - $this->origin;
    }

    /** The next frame decoded is the key frame at or before $seconds. */
    protected function rewind(float $seconds): void
    {
        if (is_null($this->decoder)) {
            return;
        }
        av_seek_frame($this->format, $this->index, (int) floor(($seconds + $this->origin) / $this->unit), AVSEEK_FLAG_BACKWARD);
        avcodec_flush_buffers($this->decoder);
        $this->draining = false;
    }

    public function close(): void
    {
        av_frame_free($this->frame);
        av_packet_free($this->packet);
        avcodec_free_context($this->decoder);
        avformat_close_input($this->format);
    }

    protected static function reason(int $status): string
    {
        $text = null;
        av_strerror($status, $text, 256);

        return $text ?: "FFmpeg error {$status}";
    }
}
