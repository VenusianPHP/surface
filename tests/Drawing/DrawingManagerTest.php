<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\DamageTrackingFramebuffer;
use Surface\Contracts\Framebuffers\ePaperFramebuffer;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\PagedFramebuffer;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Framebuffers\RingFramebuffer;
use Surface\Contracts\Rasterize\Edges;
use Surface\Drawing\DrawingManager;
use Surface\Drawing\Velvet\VelvetGE;
use Surface\EmbeddedDisplays\EmbeddedDisplay;
use Surface\Framebuffers\Native\NativeFullFramebuffer;
use Surface\NutsAndBolts\Color;
use Venusian\Surface\Tests\Support\Framebuffers\Formats;

it('gives a velvet engine over the framebuffer it is handed, a new one each time', function (): void {
    $buffer = new NativeFullFramebuffer(FormatSpec::rgba8(), 16, 8);
    $manager = drawing();
    $engine = $manager->renderer('velvet', ['framebuffer' => $buffer]);

    expect($engine)->toBeInstanceOf(VelvetGE::class)
        ->and($engine->name())->toBe('velvet')
        ->and($engine->framebuffer())->toBe($buffer)
        ->and($manager->renderer('velvet', ['framebuffer' => $buffer]))->not->toBe($engine)
        ->and($manager->engines())->toBe(['velvet']);

    $engine->frame(fn (RenderingEngine $g) => $g->fillRect(0, 0, 2, 2, Color::rgb(255, 0, 0)));
    expect($buffer->getPixel(1, 1))->toBe(0xFF0000FF);
});

it('names velvet as the default engine, and takes another from config', function (): void {
    expect(drawing()->getDefaultEngine())->toBe('velvet')
        ->and(drawing()->renderer(args: ['width' => 4, 'height' => 4]))->toBeInstanceOf(VelvetGE::class)
        ->and(drawing(['drawing.default' => 'metal'])->getDefaultEngine())->toBe('metal');
});

it('mints the framebuffer when none is handed in: a mode, a size, a format', function (): void {
    $manager = drawing();
    $buffer = fn (array $args) => $manager->renderer('velvet', $args)->framebuffer();

    $full = $buffer(['width' => 20, 'height' => 10]);
    expect([$full->viewportWidth(), $full->viewportHeight()])->toBe([20, 10])
        ->and($full->hostFormat())->toEqual(FormatSpec::rgba8())
        ->and($full->pointer() !== 0)->toBe(class_exists(FbBuffer::class))     // config's framebuffer driver: auto
        ->and($buffer(['width' => 8, 'height' => 8, 'mode' => 'dirty']))->toBeInstanceOf(DamageTrackingFramebuffer::class)
        ->and($buffer(['width' => 8, 'height' => 8, 'mode' => 'epaper', 'format' => Formats::planarBwr()]))->toBeInstanceOf(ePaperFramebuffer::class)
        ->and($buffer(['width' => 8, 'height' => 16, 'mode' => 'paged', 'page_rows' => 4, 'format' => Formats::rgb565()])->pages())->toBe(4)
        ->and($buffer(['width' => 8, 'height' => 8, 'mode' => 'ring'])->frames())->toBe(2)
        ->and($buffer(['width' => 8, 'height' => 8, 'mode' => 'ring', 'frames' => 3])->frames())->toBe(3)
        ->and($buffer(['width' => 8, 'height' => 8, 'mode' => 'paged', 'page_rows' => 2]))->toBeInstanceOf(PagedFramebuffer::class)
        ->and($buffer(['width' => 8, 'height' => 8, 'mode' => 'ring']))->toBeInstanceOf(RingFramebuffer::class);
});

it('takes the drivers and the edges by name', function (): void {
    $engine = drawing()->renderer('velvet', ['width' => 4, 'height' => 4, 'framebuffers' => 'native', 'rasterize' => 'native', 'edges' => Edges::HARD]);

    expect($engine->edges())->toBe(Edges::HARD)
        ->and(drawing()->renderer('velvet', ['width' => 4, 'height' => 4, 'edges' => 'antialiased'])->edges())->toBe(Edges::ANTIALIASED)
        ->and(drawing(['rasterize.default' => 'nowhere'])->renderer('velvet', ['width' => 4, 'height' => 4, 'rasterize' => 'native']))->toBeInstanceOf(VelvetGE::class);
});

it('mints its framebuffer in C when asked, with ext-fb loaded', function (): void {
    expect(drawing()->renderer('velvet', ['width' => 4, 'height' => 4, 'framebuffers' => 'extended'])->framebuffer()->pointer())->not->toBe(0)
        ->and(drawing(['framebuffers.default' => 'extended'])->renderer('velvet', ['width' => 4, 'height' => 4])->framebuffer()->pointer())->not->toBe(0);
})->skip(! class_exists(FbBuffer::class), 'ext-fb 0.10 is not loaded in this PHP.');

it('refuses arguments it cannot build an engine from, saying which', function (array $args, string $message): void {
    expect(fn () => drawing()->renderer('velvet', $args))->toThrow(DrawingException::class, $message);
})->with([
    'nothing to draw into' => [[], "velvet needs a 'framebuffer', or a 'width' and a 'height' to make one"],
    'a width alone' => [['width' => 8], "velvet needs a 'framebuffer', or a 'width' and a 'height' to make one"],
    'a size that is no integer' => [['width' => '8', 'height' => 8], "'width' and 'height' are integers"],
    'something that is no framebuffer' => [['framebuffer' => 'screen'], "'framebuffer' is a Framebuffer"],
    'a framebuffer and a size' => [['framebuffer' => new NativeFullFramebuffer(FormatSpec::rgba8(), 2, 2), 'width' => 8, 'height' => 8], "'framebuffer' comes alone: 'width' describes one to be made"],
    'an unknown mode' => [['width' => 8, 'height' => 8, 'mode' => 'triple'], "'mode' is one of full, dirty, epaper, paged, ring, got 'triple'"],
    'paged without its page rows' => [['width' => 8, 'height' => 8, 'mode' => 'paged'], "'paged' needs 'page_rows'"],
    'a format that is no FormatSpec' => [['width' => 8, 'height' => 8, 'format' => 'rgb565'], "'format' is a FormatSpec"],
    'edges that are neither' => [['width' => 8, 'height' => 8, 'edges' => 'soft'], "'edges' is 'hard' or 'antialiased', got 'soft'"],
    'a misspelt argument' => [['width' => 8, 'hieght' => 8], "velvet does not take 'hieght'. It takes: output, framebuffer, width, height, mode, format, page_rows, frames, framebuffers, rasterize, edges."],
]);

it('says which package brings an engine that is not installed', function (string $engine, string $package): void {
    expect(fn () => drawing()->renderer($engine))->toThrow(DrawingException::class, "No rendering engine named '{$engine}' is registered (registered: velvet). It comes with {$package}.");
})->with([['metal', 'jovian/venusian-metal'], ['opengl', 'jovian/venusian-opengl'], ['vulkan', 'jovian/venusian-vulkan'], ['sdl3', 'jovian/venusian-sdl3']]);

it('refuses an engine nobody knows', function (): void {
    expect(fn () => drawing()->renderer('crayon'))->toThrow(DrawingException::class, "No rendering engine named 'crayon' is registered (registered: velvet).");
});

it('takes engines registered by other packages, and hands them their arguments', function (): void {
    $manager = drawing();
    $seen = null;
    $manager->extend('recording', function (array $args, DrawingManager $from) use (&$seen, $manager): RenderingEngine {
        $seen = [$args, $from === $manager];

        return new RecordingEngine();
    });

    expect($manager->renderer('recording', ['headless' => true]))->toBeInstanceOf(RecordingEngine::class)
        ->and($seen)->toBe([['headless' => true], true])
        ->and($manager->engines())->toBe(['velvet', 'recording']);
});

it('draws for a target over the target framebuffer', function (): void {
    $panel = new FakeWindowPanel(16, 8, rgb565());
    $display = new EmbeddedDisplay('tft', $panel, framebuffers(), ['refreshing' => 'epaper', 'addressable' => 'dirty', 'whole' => 'full']);

    $engine = drawing()->renderer('velvet', ['output' => $display]);

    expect($engine)->toBeInstanceOf(VelvetGE::class)
        ->and($engine->framebuffer())->toBe($display->boundFramebuffer())
        ->and([$engine->framebuffer()->viewportWidth(), $engine->framebuffer()->viewportHeight()])->toBe([16, 8]);
});

it('refuses a target with framebuffer arguments, and a non-target', function (Closure $args, string $message): void {
    expect(fn () => drawing()->renderer('velvet', $args()))->toThrow(DrawingException::class, $message);
})->with([
    'with width' => [fn (): array => ['output' => fakeDisplay(), 'width' => 8], "'output' comes alone"],
    'not a target' => [fn (): array => ['output' => new stdClass], "'output' is an OutputTarget"],
]);

it('keeps a ring frame in the base engine', function (): void {
    $ring = framebuffers()->driver('native')->ring(FormatSpec::rgba8(), 16, 8, 2);
    $engine = new RecordingEngine($ring);
    $scene = fn (int $x): Closure => fn (RenderingEngine $d): RenderingEngine => $d->clear(Color::rgb(0, 0, 0))->fillRect($x, 2, 2, 2, Color::rgb(255, 255, 255));

    $ring->repair();
    $engine->frame($scene(1));
    $ring->present();
    $ring->repair();
    $engine->frame($scene(4));

    expect($engine->executed)->toHaveCount(2)
        ->and($engine->damage())->toEqual([new Region(0, 1, 7, 4)])
        ->and($engine->executed[0][0])->toBe(['clear', 0x000000FF])                    // the first frame: whole
        ->and($engine->executed[1][0])->toEqual(['clear', 0x000000FF, new Region(0, 1, 7, 4)])   // the clear, cut to the damage
        ->and($engine->executed[1][1][4])->toEqual(new Region(0, 1, 7, 4));             // the rect, clipped to it
});

it('reads the edge mode for any engine', function () {
    expect(drawing()->edgesFrom([]))->toBeNull()
        ->and(drawing()->edgesFrom(['edges' => Edges::HARD]))->toBe(Edges::HARD)
        ->and(drawing()->edgesFrom(['edges' => 'antialiased']))->toBe(Edges::ANTIALIASED);
    expect(fn () => drawing()->edgesFrom(['edges' => 'soft']))->toThrow(DrawingException::class, "'edges' is 'hard' or 'antialiased', got 'soft'.");
    expect(fn () => drawing()->edgesFrom(['edges' => 4]))->toThrow(DrawingException::class, "'edges' is 'hard' or 'antialiased', or an Edges.");
});

it('reads the framebuffer arguments for any engine that draws on the CPU', function () {
    $display = fakeDisplay();
    $handed = new NativeFullFramebuffer(FormatSpec::rgba8(), 2, 2);
    $minted = drawing()->framebufferFrom(['width' => 8, 'height' => 4, 'mode' => 'dirty', 'framebuffers' => 'native']);

    expect(drawing()->framebufferFrom(['output' => $display]))->toBe($display->boundFramebuffer())
        ->and(drawing()->framebufferFrom(['framebuffer' => $handed]))->toBe($handed)
        ->and([$minted->viewportWidth(), $minted->viewportHeight()])->toBe([8, 4])
        ->and($minted)->toBeInstanceOf(Surface\Contracts\Framebuffers\DamageTrackingFramebuffer::class);
    expect(fn () => drawing()->framebufferFrom([], 'pencil'))->toThrow(DrawingException::class, "pencil needs a 'framebuffer', or a 'width' and a 'height' to make one.");
});
