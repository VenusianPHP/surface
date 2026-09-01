<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Voyager\Contracts\IOPools\HttpDriver;
use Voyager\IOPools\HttpResult;

/** A driver that records dispatches and completes calls when told to. */
final class FakeHttpDriver implements HttpDriver
{
    /** @var list<array{name: string, method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $dispatched = [];

    /** @var list<HttpResult> */
    protected array $ready = [];

    public function dispatch(string $name, string $method, string $url, array $headers, ?string $body): void
    {
        $this->dispatched[] = ['name' => $name, 'method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
    }

    /** Queue a result for the next harvest. */
    public function complete(HttpResult $result): void
    {
        $this->ready[] = $result;
    }

    /** @var array<string, array{now: int, total: int}> What progress() answers. */
    public array $moving = [];

    public function progress(): array
    {
        return $this->moving;
    }

    public function harvest(): array
    {
        $drained = $this->ready;
        $this->ready = [];

        return $drained;
    }
}
