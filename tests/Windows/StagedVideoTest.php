<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Windows\DecodedVideo;
use Surface\Contracts\Windows\Mail\View\VideoEnded;
use Surface\Contracts\Windows\Mail\View\VideoFailed;
use Surface\Contracts\Windows\Mail\View\VideoPaused;
use Surface\Contracts\Windows\Mail\View\VideoPlaying;
use Surface\Contracts\Windows\Mail\WindowClosed;
use Surface\Contracts\Windows\NativeVideo;
use Surface\Contracts\Windows\VideoSource;
use Surface\Contracts\Windows\WindowCapability;
use Surface\Contracts\Windows\WindowException;
use Surface\Framebuffers\Extended\ExtendedFramebufferDriver;
use Surface\Windows\StagedWindow;
use Surface\Windows\Video\FfmpegStream;
use Surface\Windows\Video\FfmpegVideo;
use Venusian\Surface\Tests\Fixtures\FakeSession;

/*
 * A staged window's videos. Native runs against FakeNativeVideo (tests/Fixtures/FakeStager.php),
 * whose hooks log; Decoded runs ext-ffmpeg for real over a fixture and skips without it or ext-fb.
 */

/**
 * 1 s of 64 × 48 H.264 at 10 frames a second, frame N a flat grey of luma 16 + 20 N (so RGB about
 * 23 N: 0, 23, 47 … 210), a key frame every 5, no B-frames, no sound. Made with
 * `ffmpeg -f lavfi -i "nullsrc=s=64x48:r=10:d=1,geq=lum='16+20*N':cb=128:cr=128" -c:v libx264
 * -pix_fmt yuv420p -preset veryslow -g 5 -bf 0 -movflags +faststart`. Kept as base64 text; the
 * first call writes the file to the temp directory.
 */
function rampVideo(): string
{
    static $path = null;
    if ($path === null) {
        $bytes = base64_decode(trim((string) file_get_contents(__DIR__.'/fixtures/ramp-64x48-10fps-1s.mp4.b64')), true);
        hash('sha256', (string) $bytes) === '0fbfe2f84fb57359b6cea1da0e6fbbf0a9d7e3bce802d77b4fb87a6b8030c758' || throw new RuntimeException('tests/Windows/fixtures/ramp-64x48-10fps-1s.mp4.b64 does not decode to the fixture');
        $path = sys_get_temp_dir().'/surface-ramp-64x48-10fps-1s.mp4';
        file_put_contents($path, $bytes);
    }

    return $path;
}

function skipWithoutDecoding(): void
{
    if (! FfmpegVideo::available()) {
        test()->markTestSkipped('needs ext-ffmpeg and ext-fb');
    }
}

/** A clock the test moves: seconds in, nanoseconds out. */
final class VideoClock
{
    public float $seconds = 0.0;

    public function __invoke(): int
    {
        return (int) round($this->seconds * 1e9);
    }
}

function videoWindow(array $capabilities = [WindowCapability::Video]): FakeStagedWindow
{
    FakeStagedWindow::$capabilities = $capabilities;

    return (new FakeStager(new FakeSession()))->openStaged('game', 320, 240);
}

/** A decoded video of the fixture on $clock, in a window of its own. @return array{FfmpegVideo, VideoClock, FakeStagedWindow} */
function decoded(?string $file = null): array
{
    $clock = new VideoClock();
    $window = videoWindow();
    $video = new FfmpegVideo('clip', $window, $file ?? rampVideo(), fn (int $width, int $height) => (new ExtendedFramebufferDriver())->full(FormatSpec::rgba8(), $width, $height), $clock(...));

    return [$video, $clock, $window];
}

/** The top-left pixel of the frame showing. @return array{int, int, int, int} */
function firstPixel(DecodedVideo $video): array
{
    return array_values(unpack('C4', $video->framebuffer()->toRgba8()));
}

afterEach(function (): void {
    FakeStagedWindow::$capabilities = [];
    ForgetfulStagedWindow::$lists = [WindowCapability::Opacity];
    StagedWindow::resolveFramebuffersUsing(null);
});

it('refuses a native video on a backend with no player, naming it', function (): void {
    $window = videoWindow([]);

    expect(fn () => $window->video(VideoSource::Native, 'clip'))
        ->toThrow(WindowException::class, "Staged window 'game' cannot play a video in a view of its own: the fake/test backend does not support it.")
        ->and($window->videos())->toBe([]);
});

it('says so when a stager lists Video and makes none', function (): void {
    ForgetfulStagedWindow::$lists = [WindowCapability::Video];
    $window = new ForgetfulStagedWindow(new FakeSession());

    expect(fn () => $window->video(VideoSource::Native, 'clip'))
        ->toThrow(WindowException::class, 'ForgetfulStagedWindow lists the video capability but does not override its apply hook.');
});

it('makes the stager\'s video for Native, loaded and hidden, and keeps it by name', function (): void {
    $window = videoWindow();
    $video = $window->video(VideoSource::Native, 'clip', '/tmp/a.mp4');

    expect($video)->toBeInstanceOf(NativeVideo::class)
        ->and($video->source())->toBe(VideoSource::Native)
        ->and($video->name())->toBe('clip')
        ->and($video->window())->toBe($window)
        ->and($video->file())->toBe('/tmp/a.mp4')
        ->and($video->uuid())->toMatch('/^[0-9a-f-]{36}$/')
        ->and($video->isShown())->toBeFalse()
        ->and($video->frame())->toBeNull()
        ->and($video->log)->toBe(['file:/tmp/a.mp4'])
        ->and($window->videos())->toBe(['clip' => $video])
        ->and($window->log)->toContain('video:clip');
});

it('refuses a name taken or malformed, whatever the source', function (): void {
    $window = videoWindow();
    $window->video(VideoSource::Native, 'clip');

    expect(fn () => $window->video(VideoSource::Native, 'clip'))->toThrow(WindowException::class, "Staged window 'game' already has a video 'clip'.")
        ->and(fn () => $window->video(VideoSource::Decoded, 'clip'))->toThrow(WindowException::class, "already has a video 'clip'")
        ->and(fn () => $window->video(VideoSource::Native, 'a.b'))->toThrow(WindowException::class, "'a.b' must match [A-Za-z0-9_-]+")
        ->and(array_keys($window->videos()))->toBe(['clip']);
});

it('passes playback to the hooks and reads the position and duration back', function (): void {
    $video = videoWindow()->video(VideoSource::Native, 'clip', '/tmp/a.mp4');
    $video->length = 12.5;
    $video->play()->seek(3.0)->pause()->setMuted(true)->setLoop(true)->setFile(null);

    expect($video->log)->toBe(['file:/tmp/a.mp4', 'play', 'seek:3', 'pause', 'muted:1', 'loop:1', 'file:none'])
        ->and($video->position())->toBe(3.0)
        ->and($video->duration())->toBe(12.5)
        ->and($video->isMuted())->toBeTrue()
        ->and($video->isLooping())->toBeTrue()
        ->and($video->file())->toBeNull()
        ->and(fn () => $video->seek(-1.0))->toThrow(WindowException::class, 'Seek needs finite seconds >= 0')
        ->and(fn () => $video->seek(NAN))->toThrow(WindowException::class);
});

it('is playing when its source says so, not when play() was called', function (): void {
    $video = videoWindow()->video(VideoSource::Native, 'clip');
    $video->play();
    expect($video->isPlaying())->toBeFalse();

    $video->nativeStateChanged(true);
    expect($video->isPlaying())->toBeTrue();
});

it('places the view and shows only what is inside the clip', function (): void {
    $video = videoWindow()->video(VideoSource::Native, 'clip');
    $video->place(10.0, 20.0, 200.0, 100.0);
    $video->place(10.0, -30.0, 200.0, 100.0, [0.0, 0.0, 320.0, 240.0]);

    expect($video->log)->toBe([
        'file:none',
        'frame:10,20,200,100 visible:10,20,200,100',
        'frame:10,-30,200,100 visible:10,0,200,70',
    ])
        ->and($video->isShown())->toBeTrue()
        ->and($video->frame())->toBe([10.0, -30.0, 200.0, 100.0]);
});

it('reaches the native once for the same placement', function (): void {
    $video = videoWindow()->video(VideoSource::Native, 'clip');
    $video->place(10.0, 20.0, 200.0, 100.0, [0.0, 0.0, 320.0, 240.0]);
    $video->place(10.0, 20.0, 200.0, 100.0, [0.0, 0.0, 320.0, 240.0]);

    expect($video->log)->toBe(['file:none', 'frame:10,20,200,100 visible:10,20,200,100']);
});

it('hides the view when nothing of it is inside the clip, and shows it again when placed', function (): void {
    $video = videoWindow()->video(VideoSource::Native, 'clip');
    $video->place(0.0, 0.0, 100.0, 50.0);
    $video->place(0.0, -80.0, 100.0, 50.0, [0.0, 0.0, 320.0, 240.0]);
    expect($video->isShown())->toBeFalse()->and($video->frame())->toBe([0.0, -80.0, 100.0, 50.0]);

    $video->place(0.0, 0.0, 0.0, 50.0);
    $video->hide();
    $video->place(0.0, 0.0, 100.0, 50.0);

    expect($video->log)->toBe(['file:none', 'frame:0,0,100,50 visible:0,0,100,50', 'hidden', 'frame:0,0,100,50 visible:0,0,100,50'])
        ->and($video->isShown())->toBeTrue();
});

it('refuses a placement that is not finite or a clip that is not four numbers', function (): void {
    $video = videoWindow()->video(VideoSource::Native, 'clip');

    expect(fn () => $video->place(INF, 0.0, 1.0, 1.0))->toThrow(WindowException::class, "Video 'clip' is placed with finite numbers")
        ->and(fn () => $video->place(0.0, 0.0, 1.0, 1.0, [0.0, 0.0, NAN, 1.0]))->toThrow(WindowException::class, 'finite numbers')
        ->and(fn () => $video->place(0.0, 0.0, 1.0, 1.0, [0.0, 0.0, 1.0]))->toThrow(WindowException::class, 'takes a clip of [x, y, width, height], got 3 numbers')
        ->and($video->log)->toBe(['file:none']);
});

it('removes a video once: released, its name free, every later call refused', function (): void {
    $window = videoWindow();
    $video = $window->video(VideoSource::Native, 'clip');
    $video->nativeStateChanged(true);
    $video->remove();

    expect($video->isRemoved())->toBeTrue()
        ->and($video->isPlaying())->toBeFalse()
        ->and($video->log)->toBe(['file:none', 'release'])
        ->and($window->videos())->toBe([])
        ->and(fn () => $video->play())->toThrow(WindowException::class, "Video 'clip' was removed.")
        ->and(fn () => $video->place(0.0, 0.0, 1.0, 1.0))->toThrow(WindowException::class, 'was removed')
        ->and(fn () => $video->remove())->toThrow(WindowException::class, 'was removed')
        ->and($window->video(VideoSource::Native, 'clip'))->not->toBe($video);
});

it('removes its videos before the native window goes, on close() and on a native close', function (string $how): void {
    $window = videoWindow();
    $first = $window->video(VideoSource::Native, 'first');
    $second = $window->video(VideoSource::Native, 'second');
    $how === 'close' ? $window->close() : $window->nativeClosed();

    expect($first->isRemoved())->toBeTrue()
        ->and($second->isRemoved())->toBeTrue()
        ->and($window->videos())->toBe([])
        ->and($window->session()->outbox())->toEqual([new WindowClosed('game')]);
    if ($how === 'close') {
        expect(array_slice($window->log, -1))->toBe(['destroy']);
    }
    expect(fn () => $window->video(VideoSource::Native, 'late'))->toThrow(WindowException::class, 'was closed');
})->with(['close', 'native']);

it('opens a file\'s picture: its size, rate and length, and the first frame already in an RGBA8 framebuffer', function (): void {
    skipWithoutDecoding();
    [$video, , $window] = decoded();

    expect($video)->toBeInstanceOf(DecodedVideo::class)
        ->and($video->source())->toBe(VideoSource::Decoded)
        ->and($video->size())->toBe([64, 48])
        ->and($video->frameRate())->toBe(10.0)
        ->and($video->duration())->toBe(1.0)
        ->and($video->position())->toBe(0.0)
        ->and($video->isPlaying())->toBeFalse()
        ->and($video->framebuffer()->viewportWidth())->toBe(64)
        ->and($video->framebuffer()->viewportHeight())->toBe(48)
        ->and($video->framebuffer()->pointer())->not->toBe(0)
        ->and(firstPixel($video)[0])->toBeLessThan(8)
        ->and(firstPixel($video)[3])->toBe(255)
        ->and($video->advance())->toBeFalse()
        ->and($window->session()->outbox())->toBe([]);
});

it('shows the frame due at the clock, and nothing new until the next is due', function (): void {
    skipWithoutDecoding();
    [$video, $clock, $window] = decoded();
    $video->play();

    $clock->seconds = 0.31;
    expect($video->advance())->toBeTrue()
        ->and(firstPixel($video)[0])->toBeGreaterThan(64)->toBeLessThan(76)   // frame 3
        ->and($video->advance())->toBeFalse()
        ->and($video->position())->toEqualWithDelta(0.31, 1e-9);

    $clock->seconds = 0.39;
    expect($video->advance())->toBeFalse();

    $clock->seconds = 0.4;
    expect($video->advance())->toBeTrue()
        ->and(firstPixel($video)[0])->toBeGreaterThan(87)->toBeLessThan(99)   // frame 4
        ->and($video->isPlaying())->toBeTrue()
        ->and($window->session()->outbox())->toEqual([new VideoPlaying('game', 'clip', $video->uuid())]);
});

it('holds the position while paused and goes on from it', function (): void {
    skipWithoutDecoding();
    [$video, $clock, $window] = decoded();
    $video->play();
    $clock->seconds = 0.2;
    $video->advance();
    $video->pause();
    $clock->seconds = 5.0;

    expect($video->position())->toEqualWithDelta(0.2, 1e-9)
        ->and($video->advance())->toBeFalse()
        ->and($video->isPlaying())->toBeFalse();

    $video->pause();
    $video->play();
    $video->play();
    $clock->seconds = 5.3;

    expect($video->position())->toEqualWithDelta(0.5, 1e-9)
        ->and($video->advance())->toBeTrue()
        ->and(firstPixel($video)[0])->toBeGreaterThan(110)->toBeLessThan(123)   // frame 5
        ->and($window->session()->outbox())->toEqual([
            new VideoPlaying('game', 'clip', $video->uuid()),
            new VideoPaused('game', 'clip', $video->uuid()),
            new VideoPlaying('game', 'clip', $video->uuid()),
        ]);
});

it('seeks to a frame between key frames, paused or playing, and back', function (): void {
    skipWithoutDecoding();
    [$video, $clock] = decoded();

    $video->seek(0.7);
    expect($video->position())->toBe(0.7)
        ->and($video->advance())->toBeTrue()
        ->and(firstPixel($video)[0])->toBeGreaterThan(157)->toBeLessThan(170);   // frame 7, past the key frame at 0.5

    $video->play();
    $video->seek(0.1);
    $clock->seconds = 0.05;
    expect($video->advance())->toBeTrue()
        ->and(firstPixel($video)[0])->toBeGreaterThan(17)->toBeLessThan(30)      // frame 1
        ->and($video->position())->toEqualWithDelta(0.15, 1e-9);

    $video->seek(99.0);
    expect($video->position())->toBe(1.0);
});

it('ends after the last frame has had its time, and plays again from the start', function (): void {
    skipWithoutDecoding();
    [$video, $clock, $window] = decoded();
    $video->play();

    $clock->seconds = 0.95;
    expect($video->advance())->toBeTrue()
        ->and(firstPixel($video)[0])->toBeGreaterThan(203)->toBeLessThan(217)   // frame 9, the last
        ->and($video->isPlaying())->toBeTrue();

    $clock->seconds = 1.2;
    expect($video->advance())->toBeFalse()
        ->and($video->isPlaying())->toBeFalse()
        ->and($video->position())->toBe(1.0)
        ->and($video->advance())->toBeFalse()
        ->and($window->session()->outbox())->toEqual([
            new VideoPlaying('game', 'clip', $video->uuid()),
            new VideoEnded('game', 'clip', $video->uuid()),
        ]);

    $video->play();
    expect($video->position())->toBe(0.0)
        ->and($video->advance())->toBeTrue()
        ->and(firstPixel($video)[0])->toBeLessThan(8)
        ->and($video->isPlaying())->toBeTrue();
});

it('starts over at the end while looping, posting nothing', function (): void {
    skipWithoutDecoding();
    [$video, $clock, $window] = decoded();
    $video->setLoop(true)->play();
    $clock->seconds = 1.05;
    $video->advance();

    expect($video->isPlaying())->toBeTrue()
        ->and($video->position())->toBe(0.0);

    $clock->seconds = 1.25;
    expect($video->advance())->toBeTrue()
        ->and(firstPixel($video)[0])->toBeGreaterThan(40)->toBeLessThan(53)     // frame 2 of the second round
        ->and($window->session()->outbox())->toEqual([new VideoPlaying('game', 'clip', $video->uuid())]);
});

it('shows what it reached when a call runs out of time, and catches up on the next', function (): void {
    skipWithoutDecoding();
    $clock = new VideoClock();
    // Every read of the clock is 5 ms later: the budget of 12 ms is spent a few frames in.
    $ticking = new class($clock) {
        public function __construct(private VideoClock $clock) {}
        public function __invoke(): int { $this->clock->seconds += 0.005; return ($this->clock)(); }
    };
    $slow = new FfmpegVideo('slow', videoWindow(), rampVideo(), fn (int $width, int $height) => (new ExtendedFramebufferDriver())->full(FormatSpec::rgba8(), $width, $height), $ticking(...));
    $slow->seek(0.9);

    expect($slow->advance())->toBeTrue();
    $partial = firstPixel($slow)[0];
    expect($partial)->toBeGreaterThan(110)->toBeLessThan(203);                   // between the key frame at 0.5 and frame 9

    $calls = 0;
    while (firstPixel($slow)[0] < 203 && $calls++ < 10) {
        $slow->advance();
    }
    expect(firstPixel($slow)[0])->toBeGreaterThan(203)->toBeLessThan(217)
        ->and($calls)->toBeLessThan(10);
});

it('reports a file it cannot open with FFmpeg\'s reason and never plays it', function (): void {
    skipWithoutDecoding();
    [$video, , $window] = decoded('/nonexistent/video.mp4');
    $video->play();

    $mail = $window->session()->outbox();
    expect($mail)->toHaveCount(1)
        ->and($mail[0])->toBeInstanceOf(VideoFailed::class)
        ->and($mail[0]->name())->toBe('view.video-failed.game.clip')
        ->and($mail[0]->reason)->toBe("Video file '/nonexistent/video.mp4' cannot be opened: No such file or directory.")
        ->and($video->framebuffer())->toBeNull()
        ->and($video->size())->toBeNull()
        ->and($video->duration())->toBeNull()
        ->and($video->isPlaying())->toBeFalse()
        ->and($video->advance())->toBeFalse();
});

it('reports a file with no picture', function (): void {
    skipWithoutDecoding();
    $text = tempnam(sys_get_temp_dir(), 'surface-not-video');
    file_put_contents($text, str_repeat("not a video\n", 64));
    [$video, , $window] = decoded($text);
    unlink($text);

    expect($window->session()->outbox())->toHaveCount(1)
        ->and($window->session()->outbox()[0])->toBeInstanceOf(VideoFailed::class)
        ->and($video->framebuffer())->toBeNull();
});

it('unloads and loads another file, paused at its start', function (): void {
    skipWithoutDecoding();
    [$video, $clock, $window] = decoded();
    $video->play();
    $clock->seconds = 0.5;
    $video->advance();

    $video->setFile(null);
    expect($video->framebuffer())->toBeNull()->and($video->isPlaying())->toBeFalse()->and($video->position())->toBe(0.0);

    $video->setFile(rampVideo());
    expect($video->size())->toBe([64, 48])
        ->and($video->isPlaying())->toBeFalse()
        ->and(firstPixel($video)[0])->toBeLessThan(8)
        ->and($window->session()->outbox())->toEqual([new VideoPlaying('game', 'clip', $video->uuid())]);
});

it('makes the decoded video for Decoded on any stager, over ext-fb memory whatever the default driver', function (): void {
    skipWithoutDecoding();
    $window = videoWindow([]);
    $asked = [];
    StagedWindow::resolveFramebuffersUsing(function (?string $driver) use (&$asked) {
        $asked[] = $driver;

        return new ExtendedFramebufferDriver();
    });
    $video = $window->video(VideoSource::Decoded, 'clip', rampVideo());

    expect($video)->toBeInstanceOf(DecodedVideo::class)
        ->and($video->size())->toBe([64, 48])
        ->and($asked)->toBe(['extended'])
        ->and($window->videos())->toBe(['clip' => $video]);

    $window->close();
    expect($video->isRemoved())->toBeTrue()
        ->and(fn () => $video->advance())->toThrow(WindowException::class, "Video 'clip' was removed.");
});

it('reads a stream frame by frame, in order, and again after a seek', function (): void {
    skipWithoutDecoding();
    $stream = new FfmpegStream(rampVideo());
    $times = [];
    while (! is_null($at = $stream->next())) {
        $times[] = round($at, 3);
    }

    expect([$stream->width, $stream->height, $stream->frameRate, $stream->duration])->toBe([64, 48, 10.0, 1.0])
        ->and($times)->toBe([0.0, 0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9])
        ->and($stream->next())->toBeNull();

    $stream->seek(0.7);
    expect(round($stream->next(), 3))->toBe(0.5);                                // the key frame before it

    $stream->close();
    $stream->close();
    expect($stream->next())->toBeNull();
});
