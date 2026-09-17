<?php

use Surface\HumanInput\HumanInputManager;
use Surface\HumanInput\HumanInputResourceDriver;
use Surface\HumanInput\HumanInputServiceProvider;
use Voyager\Contracts\IOPools\IOResourceDriver;

it('registers the input resource only once the application has booted, so it ticks after os', function () {
    [$manager, $dock] = inputManager();
    $dock->resource('os', new class implements IOResourceDriver {
        public function tick(): void {}
    });
    $app = new class($manager, $dock)
    {
        /** @var list<callable> */
        public array $booted = [];

        public function __construct(private HumanInputManager $manager, private $dock) {}

        public function booted(callable $callback): void
        {
            $this->booted[] = $callback;
        }

        public function make(string $abstract): mixed
        {
            return match ($abstract) {
                HumanInputManager::class => $this->manager,
                'io-pool' => $this->dock,
            };
        }

        public function configPath(string $path = ''): string
        {
            return "/config/{$path}";
        }
    };

    (new HumanInputServiceProvider($app))->boot();

    expect($dock->resources()->has('input'))->toBeFalse();

    foreach ($app->booted as $callback) {
        $callback();
    }

    expect($dock->resources()->keys()->all())->toBe(['os', 'input'])
        ->and($dock->resources()->get('input'))->toBeInstanceOf(HumanInputResourceDriver::class);
});
