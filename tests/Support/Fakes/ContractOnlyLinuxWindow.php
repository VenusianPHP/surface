<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\NativeWindows\LinuxOSWindow;

/**
 * A LinuxOSWindow built from the contract alone, with no public $name field.
 *
 * Windowable happens to expose a public readonly $name, so a driver reaching for
 * the property instead of name() still works against the shipped delegates. This
 * fake is the one that tells the difference.
 */
final class ContractOnlyLinuxWindow implements LinuxOSWindow
{
    private ?string $window_title = null;

    private bool $presenting = false;

    public function __construct(private readonly string $window_name) {}

    public function name(): string
    {
        return $this->window_name;
    }

    public function destroy(): void
    {
        $this->presenting = false;
    }

    public function present(): void
    {
        $this->presenting = true;
    }

    public function getTitle(): ?string
    {
        return $this->window_title;
    }

    public function isPresenting(): bool
    {
        return $this->presenting;
    }

    public function setTitle(string $title): static
    {
        $this->window_title = $title;

        return $this;
    }

    public function setMenuBar(string $profile): static
    {
        return $this;
    }

    public function setEventSink(\Voyager\Contracts\IOPools\EventSink $sink): static
    {
        return $this;
    }

    public function label(string $name, string $text, int $x, int $y, int $width, int $height): \Surface\Contracts\NativeWindows\Views\OSLabel
    {
        throw new \LogicException('not conjurable');
    }

    public function view(string $name): ?\Surface\Contracts\NativeWindows\Views\OSView
    {
        return null;
    }

    public function button(string $name, string $label, int $x, int $y, int $width, int $height): \Surface\Contracts\NativeWindows\Views\OSButton
    {
        throw new \LogicException('not conjurable');
    }

    public function syncLayout(): bool
    {
        return false;
    }

    public function showAbout(): void
    {
    }

    public function title(?string $title = null): string|static|null
    {
        return is_null($title) ? $this->getTitle() : $this->setTitle($title);
    }
}
