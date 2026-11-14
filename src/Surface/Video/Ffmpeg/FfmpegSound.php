<?php

namespace Surface\Video\Ffmpeg;

use AVChannelLayout;
use Surface\Contracts\Video\SoundStream;
use Surface\Contracts\Video\VideoException;
use SwrContext;

/**
 * A source's best audio stream, resampled by libswresample to interleaved
 * signed 16-bit PCM at the rate and channels asked for, written at the
 * address it is given. What a decoded frame holds past the room offered stays
 * inside the resampler until the next read().
 */
final class FfmpegSound extends FfmpegSource implements SoundStream
{
    private ?SwrContext $resampler = null;

    /** The time of the next frame read() writes. */
    private float $at = 0.0;

    /** The resampler holds frames a read() has not taken yet. */
    private bool $holding = false;

    /** The source has ended: the resampler gives what it still holds, then nothing. */
    private bool $ended = false;

    /** Frames decoded that end before this time are passed over: where the last seek() asked to be. */
    private float $from = 0.0;

    /**
     * @throws VideoException With FFmpeg's reason.
     */
    public function __construct(private readonly string $source, private readonly int $rate, private readonly int $channels)
    {
        $this->open($source, AVMEDIA_TYPE_AUDIO);
        $this->resample();
    }

    public function read(int $address, int $frames): ?array
    {
        if ($frames < 1 || is_null($this->decoder)) {
            return null;
        }
        while (true) {
            if ($this->holding) {
                // No new input: an empty count of the held frame's planes, since a null input ends the resampler's stream.
                $wrote = swr_convert($this->resampler, [$address], $frames, $this->ended ? null : $this->frame->data(), 0);
                if ($wrote > 0) {
                    return $this->wrote($wrote);
                }
                $this->holding = false;
            }
            if ($this->ended) {
                return null;
            }
            if (! $this->receive()) {
                // What the resampler kept back for its filter comes out now.
                $this->ended = true;
                $this->holding = true;

                continue;
            }
            $count = $this->frame->nb_samples();
            $time = $this->time();
            if (! is_null($time) && $time + $count / max(1, $this->frame->sample_rate()) <= $this->from) {
                continue;
            }
            $this->at = $time ?? $this->at;
            $this->holding = true;
            $wrote = swr_convert($this->resampler, [$address], $frames, $this->frame->data(), $count);
            if ($wrote > 0) {
                return $this->wrote($wrote);
            }
        }
    }

    public function seek(float $seconds): void
    {
        $this->rewind($seconds);
        // What the resampler holds is from before the seek: a new one holds nothing.
        $this->resample();
        $this->from = $seconds;
        $this->at = $seconds;
        $this->holding = false;
        $this->ended = false;
    }

    public function close(): void
    {
        swr_free($this->resampler);
        parent::close();
    }

    /** @return array{float, int} */
    private function wrote(int $frames): array
    {
        $time = $this->at;
        $this->at += $frames / $this->rate;

        return [$time, $frames];
    }

    /**
     * @throws VideoException When libswresample has no conversion from the stream's format.
     */
    private function resample(): void
    {
        swr_free($this->resampler);
        $in = $this->decoder->ch_layout();
        if ($in->order === AV_CHANNEL_ORDER_UNSPEC) {
            // A stream that counts its channels and does not name them: the usual layout for that count.
            av_channel_layout_default($in, $in->nb_channels);
        }
        $out = new AVChannelLayout;
        av_channel_layout_default($out, $this->channels);
        $status = swr_alloc_set_opts2($this->resampler, $out, AV_SAMPLE_FMT_S16, $this->rate, $in, $this->decoder->sample_fmt(), $this->decoder->sample_rate(), 0, 0);
        $status = $status < 0 ? $status : swr_init($this->resampler);
        if ($status < 0) {
            $this->close();

            throw VideoException::unreadable($this->source, 'its sound does not convert to 16-bit PCM: '.self::reason($status));
        }
    }
}
