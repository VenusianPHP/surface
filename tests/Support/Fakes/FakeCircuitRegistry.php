<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use GeneralPurposeIO\Contracts\IntegratedCircuits\IntegratedCircuit;
use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplayException;

/**
 * The GPIO catalog, standing in. Shaped like the real CircuitRegistry's one
 * verb Surface uses; chips are handed over by "slug" or "slug.config", not built.
 */
final class FakeCircuitRegistry
{
    /** @var list<array{string, ?string}> */
    public array $asked = [];

    /** @param array<string, IntegratedCircuit> $chips */
    public function __construct(private array $chips = []) {}

    public function conjure(string $slug, ?string $config = null): IntegratedCircuit
    {
        $this->asked[] = [$slug, $config];
        $key = is_null($config) ? $slug : "{$slug}.{$config}";

        return $this->chips[$key] ?? throw new EmbeddedDisplayException("No circuit [{$key}].");
    }
}
