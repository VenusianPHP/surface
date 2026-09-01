<?php

use Voyager\Contracts\IOPools\IOPoolsException;
use Voyager\IOPools\HttpResult;
use Voyager\IOPools\HttpPool;
use Surface\Core\ProgramShuttle;
use Voyager\IOPools\EventQueue;
use Venusian\Surface\Tests\Bridge\Fakes\FakeSession;
use Venusian\Surface\Tests\Support\Fakes\FakeHttpDriver;
use Venusian\Surface\Tests\Support\Fakes\FakeWindowDriver;

function asyncShuttle(): array
{
    $driver = new FakeHttpDriver();
    $program = new class((new FakeSession())->connect(), new FakeWindowDriver(), $driver) extends ProgramShuttle {
        public function __construct($s, $w, protected FakeHttpDriver $fake_driver) { parent::__construct($s, $w); }
        protected function makeHttpDriver(): \Voyager\Contracts\IOPools\HttpDriver { return $this->fake_driver; }
    };

    return [$program, $driver];
}

function okResult(string $name, string $body = '{"ok":true}'): HttpResult
{
    return new HttpResult($name, true, 200, ['Content-Type' => 'application/json'], $body);
}

it('dispatches through the driver with an uppercased method', function () {
    [$program, $driver] = asyncShuttle();

    $program->callHttp('api-somewhere', 'get', 'https://somewhere.net/api', ['X-Key' => 'k'], null);

    expect($driver->dispatched)->toHaveCount(1)
        ->and($driver->dispatched[0]['method'])->toBe('GET')
        ->and($driver->dispatched[0]['headers'])->toBe(['X-Key' => 'k']);
});

it('delivers completion as a TASK event named exactly what the author named it', function () {
    [$program, $driver] = asyncShuttle();
    $program->callHttp('api-somewhere', 'get', 'https://somewhere.net/api');
    $driver->complete(okResult('api-somewhere'));

    $program->tick(0);

    $event = $program->events()->get('api-somewhere');
    expect($event->family)->toBe('task')
        ->and($event->payload['ok'])->toBeTrue()
        ->and($event->payload['status'])->toBe(200)
        ->and($event->payload['body'])->toBe('{"ok":true}');
});

it('fires onSuccess with the result inside the harvesting tick', function () {
    [$program, $driver] = asyncShuttle();
    $seen = null;
    $call = $program->callHttp('w', 'get', 'https://x')->onSuccess(function (HttpResult $r) use (&$seen) { $seen = $r; });
    $driver->complete(okResult('w'));

    $program->tick(0);

    expect($seen)->toBeInstanceOf(HttpResult::class)
        ->and($call->settled())->toBeTrue()
        ->and($call->result()->status)->toBe(200);
});

it('fires onFail on transport failure, not on HTTP status', function () {
    [$program, $driver] = asyncShuttle();
    $failed = null; $succeeded = false;
    $program->callHttp('w', 'get', 'https://x')
        ->onSuccess(function () use (&$succeeded) { $succeeded = true; })
        ->onFail(function (HttpResult $r) use (&$failed) { $failed = $r->error; });
    $driver->complete(new HttpResult('w', false, 0, [], '', 'Could not resolve host'));

    $program->tick(0);

    expect($failed)->toBe('Could not resolve host')
        ->and($succeeded)->toBeFalse();
});

it('a 404 is a successful conversation: onSuccess, status carried', function () {
    [$program, $driver] = asyncShuttle();
    $status = null;
    $program->callHttp('w', 'get', 'https://x')->onSuccess(function (HttpResult $r) use (&$status) { $status = $r->status; });
    $driver->complete(new HttpResult('w', true, 404, [], 'nope'));

    $program->tick(0);

    expect($status)->toBe(404);
});

it('refuses a duplicate in-flight name and frees it after settling', function () {
    [$program, $driver] = asyncShuttle();
    $program->callHttp('w', 'get', 'https://x');

    expect(fn () => $program->callHttp('w', 'get', 'https://x'))
        ->toThrow(IOPoolsException::class, "already in flight");

    $driver->complete(okResult('w'));
    $program->tick(0);
    $program->events();

    $program->callHttp('w', 'get', 'https://x');
    expect($driver->dispatched)->toHaveCount(2);
});

it('completion without hooks still delivers the event', function () {
    [$program, $driver] = asyncShuttle();
    $program->callHttp('w', 'get', 'https://x');
    $driver->complete(okResult('w'));

    $program->tick(0);

    expect($program->events()->has('w'))->toBeTrue();
});

it('registered tickables ride every tick', function () {
    [$program] = asyncShuttle();
    $ticks = 0;
    $program->register(new class($ticks) implements \Voyager\Contracts\IOPools\Tickable {
        public function __construct(protected int &$count) {}
        public function tick(): void { $this->count++; }
    });

    $program->tick(0);
    $program->tick(0);

    expect($ticks)->toBe(2);
});

it('exposes the sink so outside sources deliver through events()', function () {
    [$program] = asyncShuttle();
    $pool = new HttpPool($d = new FakeHttpDriver(), $program->sink());
    $pool->call('outside', 'get', 'https://x');
    $d->complete(okResult('outside'));

    $pool->tick();

    expect($program->events()->has('outside'))->toBeTrue();
});

it('exposes one shared pool: httpPool() and callHttp() ride the same instance', function () {
    [$program, $driver] = asyncShuttle();

    $pool = $program->httpPool();

    expect($pool)->toBeInstanceOf(HttpPool::class)
        ->and($program->httpPool())->toBe($pool);

    // A call through the shuttle is a call through that same pool.
    $program->callHttp('api-somewhere', 'get', 'https://somewhere.net/api');
    expect($pool->inFlight('api-somewhere'))->not->toBeNull()
        ->and($driver->dispatched)->toHaveCount(1);

    // And the pool is on the roster: a tick settles it.
    $driver->complete(okResult('api-somewhere'));
    $program->tick(0);
    expect($pool->inFlight('api-somewhere'))->toBeNull();
});
