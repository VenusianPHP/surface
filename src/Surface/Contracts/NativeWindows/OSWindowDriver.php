<?php

namespace Surface\Contracts\NativeWindows;

interface OSWindowDriver
{
    public function destroyAll(): void;
    public function has(string $name): bool;
    public function get(string $name): ?OSWindow;
    public function add(OSWindow $window): static;
    public function presentWindow(string $name): void;
    /** @return list<OSWindow> */
    public function all(): array;
}