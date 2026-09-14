<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * A graphical day picker. The value crosses the seam as a `Y-m-d` string;
 * engines speak year / month / day ints (month 1-based on this contract).
 */
interface OSDatePicker extends OSView
{
    /** The selected day as `Y-m-d`, or null when none has been written. */
    public function date(): ?string;

    public function year(): ?int;

    public function month(): ?int;

    public function day(): ?int;

    /** Programmatic write — silent; does not fire the hook or mail. */
    public function setDate(?string $date): static;

    /** Hook invoked when the user picks a day, during the pump. Receives (int $year, int $month, int $day). */
    public function onChange(callable $hook): static;

    public function setEnabled(bool $enabled): static;

    public function isEnabled(): bool;
}
