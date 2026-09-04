<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * A closed list of options, one selected.
 */
interface OSDropdown extends OSView
{
    /** @return list<string> */
    public function options(): array;

    /** Replace the options wholesale and select one of them. */
    public function setOptions(array $options, int $selected = 0): static;

    public function selectedIndex(): int;

    public function selectedOption(): ?string;

    public function select(int $index): static;

    /** Hook invoked when the user picks, during the pump. Receives (int $index, ?string $option). */
    public function onSelect(callable $hook): static;

    public function setEnabled(bool $enabled): static;

    public function isEnabled(): bool;
}
