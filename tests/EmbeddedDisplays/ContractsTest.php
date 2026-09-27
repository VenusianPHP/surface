<?php

use Surface\Contracts\Core\SurfaceLevelException;
use Surface\Contracts\Drawing\CPUDrawTarget;
use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplay;
use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplayException;
use Surface\Contracts\EmbeddedDisplays\Events\DisplayFaulted;
use Voyager\Contracts\IOPools\Occurrence;

it('makes an embedded display a CPU draw target with a lifecycle', function () {
    expect(is_subclass_of(EmbeddedDisplay::class, CPUDrawTarget::class))->toBeTrue();

    foreach (['name', 'present', 'show', 'hide', 'isVisible', 'switchable', 'close', 'isOpen', 'faulted', 'fault', 'setPool'] as $verb) {
        expect(method_exists(EmbeddedDisplay::class, $verb))->toBeTrue();
    }
});

it('keeps GPIO out of the contract so window-only consumers never load it', function () {
    $source = file_get_contents((new ReflectionClass(EmbeddedDisplay::class))->getFileName());

    expect($source)->not->toContain('GeneralPurposeIO');
});

it('roots its exception in SurfaceLevelException and names every failure', function () {
    expect(EmbeddedDisplayException::nameTaken('oled'))->toBeInstanceOf(SurfaceLevelException::class)
        ->and(EmbeddedDisplayException::nameTaken('oled')->getMessage())->toBe("Embedded display 'oled' is already attached.")
        ->and(EmbeddedDisplayException::noSuchDisplay('oled')->getMessage())->toBe("No embedded display named 'oled'.")
        ->and(EmbeddedDisplayException::notBooted('oled')->getMessage())->toContain('has not booted')
        ->and(EmbeddedDisplayException::hostMismatch('oled', 128, 64)->getMessage())->toContain('128x64')
        ->and(EmbeddedDisplayException::pagedNeedsWindow('strip')->getMessage())->toContain('paged engine')
        ->and(EmbeddedDisplayException::notSwitchable('strip')->getMessage())->toContain('cannot be switched')
        ->and(EmbeddedDisplayException::closed('oled')->getMessage())->toBe("Embedded display 'oled' is closed.");
});

it('names fault mail display.faulted.<display>', function () {
    $mail = new DisplayFaulted('oled', 'bus gone');

    expect($mail)->toBeInstanceOf(Occurrence::class)
        ->and($mail->name)->toBe('display.faulted.oled')
        ->and($mail->display)->toBe('oled')
        ->and($mail->error)->toBe('bus gone');
});
