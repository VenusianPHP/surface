<?php

namespace Surface\Windows\Video;

use Closure;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Windows\DecodedVideo;
use Surface\Contracts\Windows\Mail\View\VideoEnded;
use Surface\Contracts\Windows\Mail\View\VideoFailed;
use Surface\Contracts\Windows\Mail\View\VideoPaused;
use Surface\Contracts\Windows\Mail\View\VideoPlaying;
use Surface\Contracts\Windows\VideoSource;
use Surface\Contracts\Windows\WindowException;
use Surface\Windows\StagedVideo;
use Surface\Windows\StagedWindow;

/**
 * VideoSource::Decoded: an FfmpegStream's frames in one RGBA8 framebuffer at
 * the picture's own size, converted in place at the framebuffer's address.
 *
 * The clock is this object's: the position is where it stood when playback
 * last started or stopped, plus the time since. advance() decodes on the
 * thread that calls it (the loop's): one frame decoded and one converted for
 * each frame shown, with the next one decoded ahead and held until it is due.
 * A frame the next is also due after is dropped unconverted, and a call far
 * behind stops after BUDGET_NS and shows what it reached, so a seek or a stall
 * catches up over a few calls and never holds the loop.
 *
 * It is the source, so it reports itself: play() and pause() post VideoPlaying
 * and VideoPaused, the last frame's end VideoEnded, a file that does not open
 * VideoFailed with FFmpeg's reason. Looping, the end rewinds and posts nothing.
 */
class FfmpegVideo extends StagedVideo implements DecodedVideo
{
    /** The longest one advance() goes on decoding late frames before it shows the one it has: 12 ms. */
    public const int BUDGET_NS = 12_000_000;

    /** A frame is due this much before its time, in seconds: a position asked for as a frame's own time reaches that frame. */
    private const float SLACK = 1e-6;

    private ?FfmpegStream $stream = null;

    private ?Framebuffer $framebuffer = null;

    /** The position when the clock last started or stopped, in seconds. */
    private float $base = 0.0;

    /** When the clock started, in the clock's nanoseconds; null while it stands. */
    private ?int $started = null;

    /** The time of the frame decoded ahead and not yet shown. */
    private ?float $held = null;

    /** Nothing of this file, or since the last seek, has been shown: the next frame decoded shows at once. */
    private bool $fresh = false;

    /** The last frame has been shown for its time: play() starts again from the start. */
    private bool $ended = false;

    /** @var Closure(): int */
    private readonly Closure $now;

    /**
     * @param  Closure(int, int): Framebuffer  $mint  an RGBA8 framebuffer of that width and height whose pointer() is the address of its bytes
     * @param  (Closure(): int)|null  $now  the clock, in nanoseconds; hrtime() when null
     */
    public function __construct(string $name, StagedWindow $window, ?string $file, private readonly Closure $mint, ?Closure $now = null)
    {
        parent::__construct($name, $window, $file);
        $this->now = $now ?? static fn (): int => hrtime(true);
        $this->applyFile($file);
    }

    /** Whether this PHP can make one: ext-ffmpeg decodes, and ext-fb gives it memory to convert into. */
    public static function available(): bool
    {
        return extension_loaded('ffmpeg') && class_exists(\FbBuffer::class);
    }

    public function source(): VideoSource
    {
        return VideoSource::Decoded;
    }

    public function framebuffer(): ?Framebuffer
    {
        return $this->framebuffer;
    }

    public function size(): ?array
    {
        return is_null($this->stream) ? null : [$this->stream->width, $this->stream->height];
    }

    public function frameRate(): float
    {
        return $this->stream?->frameRate ?? 0.0;
    }

    public function advance(): bool
    {
        $this->live();
        if (is_null($this->stream) || $this->ended) {
            return false;
        }
        $target = $this->nativePosition() + self::SLACK;
        $step = $this->stream->frameRate > 0.0 ? 1.0 / $this->stream->frameRate : 0.0;
        $deadline = ($this->now)() + self::BUDGET_NS;
        $changed = false;
        while (true) {
            $this->held ??= $this->stream->next();
            if (is_null($this->held)) {
                // Nothing left to decode: the frame showing stays for its own time, then the video has ended.
                if ($this->fresh || is_null($this->stream->duration) || $target >= $this->stream->duration) {
                    $this->finish();
                }

                break;
            }
            if ($this->held > $target && ! $this->fresh) {
                break;
            }
            $spent = ($this->now)() >= $deadline;
            // Late: the frame after it is due too. Shown anyway when this call has run out of time.
            $late = $step > 0.0 && $this->held + $step <= $target;
            if (! $late || $this->fresh || $spent) {
                $this->stream->convert($this->framebuffer->pointer());
                $this->fresh = false;
                $changed = true;
            }
            $this->held = null;
            if ($spent) {
                break;
            }
        }

        return $changed;
    }

    protected function applyFile(?string $file): void
    {
        $this->unload();
        if (is_null($file)) {
            return;
        }
        try {
            $this->stream = new FfmpegStream($file);
            $this->framebuffer = ($this->mint)($this->stream->width, $this->stream->height);
        } catch (WindowException $refused) {
            $this->unload();
            $this->post(new VideoFailed($this->window->name(), $this->path(), $this->uuid, $refused->getMessage()));

            return;
        }
        // The first frame is in the framebuffer before anyone draws it.
        $this->fresh = true;
        $this->advance();
    }

    protected function applyPlay(): void
    {
        if (is_null($this->stream) || $this->playing) {
            return;
        }
        if ($this->ended) {
            $this->rewind(0.0);
        }
        $this->started = ($this->now)();
        $this->nativeStateChanged(true);
        $this->post(new VideoPlaying($this->window->name(), $this->path(), $this->uuid));
    }

    protected function applyPause(): void
    {
        if (! $this->playing) {
            return;
        }
        $this->base = $this->nativePosition();
        $this->started = null;
        $this->nativeStateChanged(false);
        $this->post(new VideoPaused($this->window->name(), $this->path(), $this->uuid));
    }

    protected function applySeek(float $seconds): void
    {
        if (! is_null($this->stream)) {
            $this->rewind(min($seconds, $this->stream->duration ?? $seconds));
        }
    }

    /** No sound is played: the state is kept. */
    protected function applyMuted(bool $muted): void {}

    /** Read where the stream ends. */
    protected function applyLoop(bool $loop): void {}

    protected function nativePosition(): float
    {
        return $this->base + (is_null($this->started) ? 0.0 : (($this->now)() - $this->started) / 1e9);
    }

    protected function nativeDuration(): ?float
    {
        return $this->stream?->duration;
    }

    protected function releaseNative(): void
    {
        $this->unload();
    }

    /** The clock and the stream to $seconds; the frame there shows at the next advance(). */
    private function rewind(float $seconds): void
    {
        $this->stream->seek($seconds);
        $this->base = $seconds;
        $this->started = $this->playing ? ($this->now)() : null;
        $this->held = null;
        $this->fresh = true;
        $this->ended = false;
    }

    /** The stream has no more frames and the last one has had its time. */
    private function finish(): void
    {
        if ($this->looping && $this->playing) {
            $this->rewind(0.0);

            return;
        }
        $this->ended = true;
        $this->fresh = false;
        if (! $this->playing) {
            return;
        }
        $this->base = $this->stream->duration ?? $this->nativePosition();
        $this->started = null;
        $this->nativeStateChanged(false);
        $this->post(new VideoEnded($this->window->name(), $this->path(), $this->uuid));
    }

    private function unload(): void
    {
        $this->stream?->close();
        $this->stream = null;
        $this->framebuffer = null;
        $this->base = 0.0;
        $this->started = null;
        $this->held = null;
        $this->fresh = false;
        $this->ended = false;
        $this->playing = false;
    }
}
