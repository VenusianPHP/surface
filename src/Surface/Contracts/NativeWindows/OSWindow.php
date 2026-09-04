<?php

namespace Surface\Contracts\NativeWindows;

use Voyager\Contracts\IOPools\PoolPump;

interface OSWindow
{
    public function name(): string;
    public function destroy(): void;
    public function present(): void;
    public function getTitle(): ?string;
    public function isPresenting(): bool;
    public function setTitle(string $title): static;
    public function setMenuBar(string $profile): static;
    public function setPool(PoolPump $pool): static;
    public function label(string $name, string $text, int $x, int $y, int $width, int $height): Views\OSLabel;
    public function button(string $name, string $label, int $x, int $y, int $width, int $height): Views\OSButton;
    public function view(string $name): ?Views\OSView;
    public function syncLayout(): bool;
    public function showAbout(): void;
    public function title(?string $title = null): string|static|null;
}
