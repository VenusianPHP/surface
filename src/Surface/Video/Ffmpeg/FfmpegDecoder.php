<?php

namespace Surface\Video\Ffmpeg;

use Surface\Contracts\Video\PictureStream;
use Surface\Contracts\Video\SoundStream;
use Surface\Contracts\Video\VideoDecoder;
use Surface\Contracts\Video\VideoException;
use Surface\Contracts\Video\VideoInfo;

/** The 'ffmpeg' video driver: ext-ffmpeg demuxes, decodes, scales and resamples. */
final class FfmpegDecoder implements VideoDecoder
{
    /** ext-ffmpeg with av_dict_set(), which turns the decoders' threads on. */
    public static function available(): bool
    {
        return function_exists('av_dict_set');
    }

    /**
     * @throws VideoException When ext-ffmpeg is not loaded, or is a build without av_dict_set().
     */
    public function __construct()
    {
        if (! self::available()) {
            throw VideoException::extensionMissing('ffmpeg', 'ext-ffmpeg 0.11 with av_dict_set()');
        }
    }

    public function driver(): string
    {
        return 'ffmpeg';
    }

    public function probe(string $source): VideoInfo
    {
        [$format, $index] = FfmpegSource::find($source, AVMEDIA_TYPE_VIDEO);
        $stream = $format->streams()[$index];
        $parameters = $stream->codecpar();
        $rate = $stream->avg_frame_rate();
        $sound = null;
        $info = new VideoInfo(
            $parameters->width(),
            $parameters->height(),
            $rate->num > 0 && $rate->den > 0 ? $rate->num / $rate->den : 0.0,
            match (true) {
                $format->duration() !== AV_NOPTS_VALUE && $format->duration() > 0 => $format->duration() / AV_TIME_BASE,
                $stream->duration() !== AV_NOPTS_VALUE && $stream->duration() > 0 => $stream->duration() * av_q2d($stream->time_base()),
                default => null,
            },
            av_find_best_stream($format, AVMEDIA_TYPE_AUDIO, -1, $index, $sound, 0) >= 0 && ! is_null($sound),
        );
        avformat_close_input($format);
        if ($info->width < 1 || $info->height < 1) {
            throw VideoException::unreadable($source, "it does not say its picture's size");
        }

        return $info;
    }

    public function pictures(string $source): PictureStream
    {
        return new FfmpegPictures($source);
    }

    public function sound(string $source, int $rate, int $channels): SoundStream
    {
        return new FfmpegSound($source, $rate, $channels);
    }
}
