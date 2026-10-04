<?php

use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplayException;
use Surface\Contracts\EmbeddedDisplays\Mail\DisplayFaulted;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\Endianness;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\EmbeddedDisplays\EmbeddedDisplay;
use Surface\EmbeddedDisplays\EmbeddedDisplayManager;

function managerPanel(): FakeWindowPanel
{
    return new FakeWindowPanel(16, 16, new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B16, endianness: Endianness::MSB));
}

function displayManager(?Closure $catalog = null, ?Closure $post = null): EmbeddedDisplayManager
{
    return new EmbeddedDisplayManager(framebuffers(), ['refreshing' => 'epaper', 'addressable' => 'dirty', 'whole' => 'full'], $catalog, $post);
}

/** A circuit registry that answers one chip and counts the conjures. */
function fakeCatalog(object $chip): object
{
    return new class($chip) {
        /** @var list<array{string, string|null}> */
        public array $conjured = [];

        public function __construct(private readonly object $chip) {}

        public function conjure(string $panel, ?string $config = null): object
        {
            $this->conjured[] = [$panel, $config];

            return $this->chip;
        }
    };
}

it('attaches a display under a name and lists it', function () {
    $manager = displayManager();
    $display = $manager->attach(managerPanel(), 'oled');

    expect($display)->toBeInstanceOf(EmbeddedDisplay::class)
        ->and($display->name())->toBe('oled')
        ->and($display->isOpen())->toBeTrue()
        ->and($manager->display('oled'))->toBe($display)
        ->and($manager->has('oled'))->toBeTrue()
        ->and($manager->displays())->toBe(['oled' => $display]);
});

it('refuses a name already attached', function () {
    $manager = displayManager();
    $manager->attach(managerPanel(), 'oled');
    $manager->attach(managerPanel(), 'oled');
})->throws(EmbeddedDisplayException::class, "Embedded display 'oled' is already attached.");

it('refuses a panel that has not booted', function () {
    displayManager()->attach(new FakeUnbootedPanel(16, 16, FormatSpec::rgba8()), 'oled');
})->throws(EmbeddedDisplayException::class, 'has not booted');

it('refuses a panel that never says how it packs its bytes', function () {
    displayManager()->attach(new FakeFormatlessPanel, 'mystery');
})->throws(EmbeddedDisplayException::class, 'Circuit [mystery] conjured a FakeFormatlessPanel, which is not a display panel with a formatSpec().');

it('conjures a panel from the circuit catalog once, named after the panel and config', function () {
    $catalog = fakeCatalog(managerPanel());
    $manager = displayManager(fn (): object => $catalog);

    $plain = $manager->panel('ssd1306');
    $again = $manager->panel('ssd1306');
    $spi = $manager->panel('ssd1306', 'spi');

    expect($plain->name())->toBe('ssd1306')
        ->and($again)->toBe($plain)
        ->and($spi->name())->toBe('ssd1306.spi')
        ->and($manager->panel('ssd1306', 'spi', 'side')->name())->toBe('side')
        ->and($catalog->conjured)->toBe([['ssd1306', null], ['ssd1306', 'spi'], ['ssd1306', 'spi']]);
});

it('needs a circuit catalog to conjure', function () {
    displayManager()->panel('ssd1306');
})->throws(EmbeddedDisplayException::class, 'No circuit catalog is bound.');

it('refuses a conjured chip that is not a display panel', function () {
    displayManager(fn (): object => fakeCatalog(new stdClass))->panel('fan');
})->throws(EmbeddedDisplayException::class, 'Circuit [fan] conjured a stdClass');

it('detaches a display by closing it', function () {
    $manager = displayManager();
    $display = $manager->attach(managerPanel(), 'oled');

    $manager->detach('oled');

    expect($display->isOpen())->toBeFalse()
        ->and($manager->has('oled'))->toBeFalse();
});

it('names a display it does not hold', function () {
    displayManager()->display('nope');
})->throws(EmbeddedDisplayException::class, "No embedded display named 'nope'.");

it('closes every display on destroy, and rethrows the first failure after closing the rest', function () {
    $manager = displayManager();
    $failing = managerPanel();
    $first = $manager->attach($failing, 'first');
    $second = $manager->attach(managerPanel(), 'second');
    $failing->fail = new RuntimeException('bus gone');

    expect(fn () => $manager->destroy())->toThrow(RuntimeException::class, 'bus gone')
        ->and($first->isOpen())->toBeFalse()
        ->and($second->isOpen())->toBeFalse()
        ->and($manager->displays())->toBe([]);
});

it('posts a display fault through the manager', function () {
    $mail = [];
    $panel = managerPanel();
    $manager = displayManager(post: function (object $m) use (&$mail): void {
        $mail[] = $m;
    });
    $display = $manager->attach($panel, 'oled');
    $display->framebuffer();
    $panel->fail = new RuntimeException('bus gone');

    $display->present();

    expect($mail)->toHaveCount(1)
        ->and($mail[0])->toBeInstanceOf(DisplayFaulted::class)
        ->and($mail[0]->name())->toBe('display.faulted.oled');
});
