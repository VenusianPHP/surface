<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Voyager\Contracts\Config\Repository;

/** A dot-keyed array behind the config contract, enough for a Manager. */
final class FakeConfigRepository implements Repository
{
    public function __construct(private array $items = []) {}

    public function has(string $key)
    {
        return ! is_null($this->find($key));
    }

    public function get(array|string $key, mixed $default = null)
    {
        if (is_array($key)) {
            $out = [];
            foreach ($key as $k => $d) {
                $out[$k] = $this->get($k, $d);
            }

            return $out;
        }

        return $this->find($key) ?? $default;
    }

    public function all()
    {
        return $this->items;
    }

    public function set(array|string $key, mixed $value = null)
    {
        $keys = is_array($key) ? $key : [$key => $value];
        foreach ($keys as $k => $v) {
            $ref = &$this->items;
            foreach (explode('.', $k) as $segment) {
                if (! isset($ref[$segment]) || ! is_array($ref[$segment])) {
                    $ref[$segment] = [];
                }
                $ref = &$ref[$segment];
            }
            $ref = $v;
        }
    }

    public function prepend(string $key, mixed $value)
    {
        $list = $this->get($key, []);
        array_unshift($list, $value);
        $this->set($key, $list);
    }

    public function push(string $key, mixed $value)
    {
        $list = $this->get($key, []);
        $list[] = $value;
        $this->set($key, $list);
    }

    private function find(string $key): mixed
    {
        $node = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return null;
            }
            $node = $node[$segment];
        }

        return $node;
    }
}
