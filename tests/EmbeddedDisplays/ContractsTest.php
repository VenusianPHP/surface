<?php

use Surface\Contracts\Core\SurfaceException;
use Surface\Contracts\Drawing\Output;
use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplay;
use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplayException;
use Surface\Contracts\EmbeddedDisplays\Mail\DisplayFaulted;
use Voyager\Contracts\Signals\NamedSignal;

it('makes an embedded display a drawing output with a lifecycle', function () {
    expect(is_subclass_of(EmbeddedDisplay::class, Output::class))->toBeTrue();

    foreach (['name', 'framebuffer', 'bind', 'boundFramebuffer', 'present', 'show', 'hide', 'isVisible', 'switchable', 'close', 'isOpen', 'faulted', 'fault'] as $verb) {
        expect(method_exists(EmbeddedDisplay::class, $verb))->toBeTrue();
    }
});

it('keeps GPIO out of the contract so window-only consumers never load it', function () {
    $source = file_get_contents((new ReflectionClass(EmbeddedDisplay::class))->getFileName());

    expect($source)->not->toContain('GeneralPurposeIO');
});

it('names fault mail display.faulted.<display>', function () {
    $mail = new DisplayFaulted('oled', 'bus gone');

    expect($mail)->toBeInstanceOf(NamedSignal::class)
        ->and($mail->name())->toBe('display.faulted.oled')
        ->and($mail->display)->toBe('oled')
        ->and($mail->error)->toBe('bus gone');
});

it('words every display failure', function (EmbeddedDisplayException $e, string $message) {
    expect($e)->toBeInstanceOf(SurfaceException::class)->and($e->getMessage())->toBe($message);
})->with([
    'name taken' => fn () => [EmbeddedDisplayException::nameTaken('oled'), "Embedded display 'oled' is already attached."],
    'no catalog' => fn () => [EmbeddedDisplayException::noCatalog(), 'No circuit catalog is bound. Install scrapyard-io/framework to conjure a panel.'],
    'not a panel' => fn () => [EmbeddedDisplayException::notADisplayPanel('fan', 'Fan'), 'Circuit [fan] conjured a Fan, which is not a display panel with a formatSpec().'],
    'no such display' => fn () => [EmbeddedDisplayException::noSuchDisplay('oled'), "No embedded display named 'oled'."],
    'not booted' => fn () => [EmbeddedDisplayException::notBooted('oled'), "The panel for embedded display 'oled' has not booted. Boot it first (boot_now: true)."],
    'paged needs window' => fn () => [EmbeddedDisplayException::pagedNeedsWindow('ink'), "Embedded display 'ink' cannot show a paged framebuffer: its panel takes whole frames only."],
    'page rows unaligned' => fn () => [EmbeddedDisplayException::pageRowsUnaligned('oled', 12, 8), "Embedded display 'oled' needs page_rows in multiples of 8, got 12."],
    'page rows missing' => fn () => [EmbeddedDisplayException::pageRowsMissing('oled'), "Embedded display 'oled' needs page_rows for a paged framebuffer."],
    'not switchable' => fn () => [EmbeddedDisplayException::notSwitchable('ink'), "The panel for embedded display 'ink' cannot be switched on or off."],
    'closed' => fn () => [EmbeddedDisplayException::closed('oled'), "Embedded display 'oled' is closed."],
    'size mismatch' => fn () => [EmbeddedDisplayException::sizeMismatch('oled', 128, 64, 64, 32), "Embedded display 'oled' is 128x64; a 64x32 framebuffer cannot be bound to it."],
    'nothing bound' => fn () => [EmbeddedDisplayException::nothingBound('oled'), "Embedded display 'oled' has no framebuffer: call framebuffer() or bind() first."],
    'unknown kind' => fn () => [EmbeddedDisplayException::unknownKind('blurry'), "A display framebuffer is 'full', 'dirty', 'epaper', 'paged' or 'ring', got 'blurry'."],
]);
