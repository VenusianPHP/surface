<?php

use Surface\Contracts\NativeWindows\Events\View\TextChanged;
use Surface\Contracts\NativeWindows\Events\View\TextSubmitted;
use Venusian\Surface\Tests\Support\Fakes\FakeTextInput;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('conjures a text input, registers it by name and places it at once', function () {
    $window = new FakeWindow('main');

    $input = $window->textInput('query', 'mars', 10, 20, 200, 28);

    expect($input)->toBeInstanceOf(FakeTextInput::class)
        ->and($input->value())->toBe('mars')
        ->and($input->placeholder())->toBeNull()
        ->and($window->view('query'))->toBe($input)
        ->and($input->applied_frames)->toBe([[10, 20, 200, 28]]);
});

it('carries a placeholder and the secret flag through conjuring', function () {
    $window = new FakeWindow('main');

    $input = $window->textInput('pass', '', 0, 0, 200, 28, placeholder: 'Password', secret: true);

    expect($input->placeholder())->toBe('Password')
        ->and($input->secret)->toBeTrue();
});

it('writes value and placeholder through to the engine and remembers them', function () {
    $window = new FakeWindow('main');
    $input = $window->textInput('query', '', 0, 0, 200, 28);

    $input->setValue('venus')->setPlaceholder('Search…');

    expect($input->value())->toBe('venus')
        ->and($input->applied_values)->toBe(['venus'])
        ->and($input->placeholder())->toBe('Search…')
        ->and($input->applied_placeholders)->toBe(['Search…']);
});

it('an engine edit updates the value, invokes the hook, and rides the dock', function () {
    $dock = bareDock();
    $window = new FakeWindow('main');
    $window->setPool($dock);
    $seen = [];
    $input = $window->textInput('query', '', 0, 0, 200, 28)
        ->onChange(function (string $value) use (&$seen) { $seen[] = $value; });

    $input->typeText('m');
    $input->typeText('ma');

    $mail = $dock->drain()->filter(fn (object $mail) => $mail instanceof TextChanged)->values();
    expect($input->value())->toBe('ma')
        ->and($seen)->toBe(['m', 'ma'])
        ->and($mail)->toHaveCount(2)
        ->and($mail->first()->name)->toBe('main.query.changed')
        ->and($mail->last()->value)->toBe('ma');
});

it('submitting rides the dock as TextSubmitted with the current value', function () {
    $dock = bareDock();
    $window = new FakeWindow('main');
    $window->setPool($dock);
    $submitted = null;
    $input = $window->textInput('query', '', 0, 0, 200, 28)
        ->onSubmit(function (string $value) use (&$submitted) { $submitted = $value; });

    $input->typeText('europa');
    $input->submit();

    $mail = $dock->drain()->first(fn (object $mail) => $mail instanceof TextSubmitted);
    expect($submitted)->toBe('europa')
        ->and($mail)->not->toBeNull()
        ->and($mail->name)->toBe('main.query.submitted')
        ->and($mail->value)->toBe('europa');
});

it('editing with no pool and no hook is still safe', function () {
    $window = new FakeWindow('main');
    $window->textInput('query', '', 0, 0, 200, 28)->typeText('io');

    expect(true)->toBeTrue();
});

it('starts enabled and setEnabled writes through only on change', function () {
    $window = new FakeWindow('main');
    $input = $window->textInput('query', '', 0, 0, 200, 28);

    $input->disable();
    $input->disable();
    $input->enable();

    expect($input->isEnabled())->toBeTrue()
        ->and($input->applied_enabled)->toBe([false, true]);
});
