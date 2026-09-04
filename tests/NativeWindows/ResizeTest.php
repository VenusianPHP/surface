<?php

use Surface\Contracts\Core\Events\SurfaceEventType;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;
use Venusian\Surface\Tests\Support\Fakes\FakeWindowDriver;

/*
|--------------------------------------------------------------------------
| Resize follows rules, not the sketch
|--------------------------------------------------------------------------
|
| Placement is stored as a rule. When the content size changes, the window
| re-resolves every view and pushes WINDOW_RESIZED; nothing is told to move.
|
*/

it('re-centres a centred view when the content grows', function () {
    $window = new FakeWindow('main');
    $window->content_size = [400, 600];
    $label = $window->label('title', 'Hi', 0, 0, 100, 20)->center();

    $window->content_size = [800, 300];
    $window->relayout();

    expect($label->frame())->toBe(['x' => 350, 'y' => 140, 'width' => 100, 'height' => 20]);
});

it('leaves an absolutely placed view where it was', function () {
    $window = new FakeWindow('main');
    $label = $window->label('title', 'Hi', 30, 40, 100, 20);

    $window->content_size = [800, 300];
    $window->relayout();

    expect($label->frame())->toBe(['x' => 30, 'y' => 40, 'width' => 100, 'height' => 20]);
});

it('re-measures a hugged view on relayout', function () {
    $window = new FakeWindow('main');
    $label = $window->label('title', 'Hi', 10, 10, 1, 1)->hug();
    $label->natural_size = [300, 40];

    $window->relayout();

    expect($label->frame())->toBe(['x' => 10, 'y' => 10, 'width' => 300, 'height' => 40]);
});

it('a later place() drops the centre rule', function () {
    $window = new FakeWindow('main');
    $window->content_size = [400, 600];
    $label = $window->label('title', 'Hi', 0, 0, 100, 20)->center();

    $label->place(5, 5, 100, 20);
    $window->content_size = [800, 300];
    $window->relayout();

    expect($label->frame())->toBe(['x' => 5, 'y' => 5, 'width' => 100, 'height' => 20]);
});

it('lays out from a 0x0 content once the first real size arrives', function () {
    $window = new FakeWindow('main');
    $window->content_size = [0, 0];                      // GTK before first layout
    $label = $window->label('title', 'Hi', 0, 0, 100, 20)->center();
    expect($label->frame()['x'])->toBe(-50);

    $window->content_size = [400, 600];
    $changed = $window->syncLayout();

    expect($changed)->toBeTrue()
        ->and($label->frame())->toBe(['x' => 150, 'y' => 290, 'width' => 100, 'height' => 20]);
});

it('syncLayout answers false and pushes nothing while the size holds', function () {
    $dock = bareDock();
    $window = new FakeWindow('main');
    $window->setPool($dock);
    $window->syncLayout();
    $dock->drain();

    expect($window->syncLayout())->toBeFalse()
        ->and($dock->drain())->toHaveCount(0);
});

it('pushes WINDOW_RESIZED with the new size when it changes', function () {
    $dock = bareDock();
    $window = new FakeWindow('main');
    $window->setPool($dock);
    $window->syncLayout();
    $dock->drain();

    $window->content_size = [1024, 768];
    $window->syncLayout();

    $event = mailNamed($dock->drain(), 'window.resized.main');
    expect($event->type)->toBe(SurfaceEventType::WINDOW_RESIZED)
        ->and($event->width)->toBe(1024.0)
        ->and($event->height)->toBe(768.0);
});

it('tick pumps then syncs every window so layout follows the OS', function () {
    [$app, $dock] = liveApp();
    $app->provisionWindow('main', 400, 600);
    $app->provisionWindow('inspector', 200, 300);
    /** @var FakeWindow $main */
    $main = $app->getWindowService()->get('main');
    $label = $main->label('title', 'Hi', 0, 0, 100, 20)->center();
    $app->tick(16);
    $dock->drain();

    $main->content_size = [800, 300];
    $app->tick(16);

    $bag = $dock->drain();
    expect($label->frame()['x'])->toBe(350)
        ->and(mailNamed($bag, 'window.resized.main'))->not->toBeNull()
        ->and(mailNamed($bag, 'window.resized.inspector'))->toBeNull();
});

it('the driver lists every window it holds', function () {
    $driver = new FakeWindowDriver();
    $driver->add(new FakeWindow('a'))->add(new FakeWindow('b'));

    expect($driver->all())->toHaveCount(2)
        ->and($driver->all()[1]->name())->toBe('b');
});

it('keeps a centre offset through relayout so stacked views do not collide', function () {
    $window = new FakeWindow('main');
    $window->content_size = [400, 600];
    $label = $window->label('title', 'Hi', 0, 0, 100, 20)->center(0, -30);
    $button = $window->button('go', 'Go', 0, 0, 80, 30)->center(0, 30);

    $window->content_size = [800, 300];
    $window->relayout();

    expect($label->frame())->toBe(['x' => 350, 'y' => 110, 'width' => 100, 'height' => 20])
        ->and($button->frame())->toBe(['x' => 360, 'y' => 165, 'width' => 80, 'height' => 30]);
});

it('centerX re-centres horizontally on resize and leaves y anchored', function () {
    $window = new \Venusian\Surface\Tests\Support\Fakes\FakeWindow('main');
    $window->content_size = [600, 640];
    $label = $window->label('card', 'Title', 0, 40, 560, 26);

    $label->centerX();
    expect($label->frame())->toBe(['x' => 20, 'y' => 40, 'width' => 560, 'height' => 26]);

    $window->content_size = [800, 640];
    $window->syncLayout();

    expect($label->frame())->toBe(['x' => 120, 'y' => 40, 'width' => 560, 'height' => 26]);
});

it('centerX keeps its offset and survives a wrapped label re-measure', function () {
    $window = new \Venusian\Surface\Tests\Support\Fakes\FakeWindow('main');
    $window->content_size = [600, 640];
    $label = $window->label('body', 'Long text', 0, 416, 1, 1);
    $label->wrapped_height = 96;

    $label->wrap(560)->centerX(10);

    expect($label->frame())->toBe(['x' => 30, 'y' => 416, 'width' => 560, 'height' => 96]);
});
