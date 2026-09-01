<?php

namespace Venusian\Surface\Tests\Views\Fakes;

/** Records native calls made by fake handles, in order, across a whole tree. */
final class CallLog
{
    /** @var list<array{op: string, pointer: int, args: list<mixed>}> */
    public array $entries = [];

    private int $next_pointer = 100;

    public function record(string $op, int $pointer, mixed ...$args): void
    {
        $this->entries[] = ['op' => $op, 'pointer' => $pointer, 'args' => array_values($args)];
    }

    public function nextPointer(): int
    {
        return $this->next_pointer++;
    }

    /** @return list<string> */
    public function ops(): array
    {
        return array_column($this->entries, 'op');
    }

    /** @return list<array{op: string, pointer: int, args: list<mixed>}> */
    public function of(string $op): array
    {
        return array_values(array_filter($this->entries, fn (array $entry): bool => $entry['op'] === $op));
    }

    public function clear(): void
    {
        $this->entries = [];
    }
}
