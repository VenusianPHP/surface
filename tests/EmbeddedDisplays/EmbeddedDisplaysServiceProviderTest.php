<?php

use Surface\EmbeddedDisplays\EmbeddedDisplayManager;
use Surface\EmbeddedDisplays\EmbeddedDisplayResourceDriver;
use Surface\EmbeddedDisplays\EmbeddedDisplaysServiceProvider;
use Voyager\Contracts\IOPools\IOResourceDriver;

it('registers the displays resource only once the application has booted', function () {
    [$manager, $dock] = displayManager();
    $dock->resource('os', new class implements IOResourceDriver {
        public function tick(): void {}
    });
    $app = new class($manager, $dock)
    {
        /** @var list<callable> */
        public array $booted = [];

        public function __construct(private EmbeddedDisplayManager $manager, private $dock) {}

        public function booted(callable $callback): void
        {
            $this->booted[] = $callback;
        }

        public function make(string $abstract): mixed
        {
            return match ($abstract) {
                EmbeddedDisplayManager::class => $this->manager,
                'io-pool' => $this->dock,
            };
        }

        public function configPath(string $path = ''): string
        {
            return "/config/{$path}";
        }
    };

    (new EmbeddedDisplaysServiceProvider($app))->boot();

    expect($dock->resources()->has('displays'))->toBeFalse();

    foreach ($app->booted as $callback) {
        $callback();
    }

    expect($dock->resources()->keys()->all())->toBe(['os', 'displays'])
        ->and($dock->resources()->get('displays'))->toBeInstanceOf(EmbeddedDisplayResourceDriver::class);
});
