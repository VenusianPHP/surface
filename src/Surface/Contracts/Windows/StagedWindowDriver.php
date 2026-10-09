<?php

namespace Surface\Contracts\Windows;

/**
 * A toolkit that stages windows. Named apart from ToolkitWindowDriver so one
 * driver may implement both.
 */
interface StagedWindowDriver extends WindowDriver
{
    /**
     * @param  array<string, mixed>  $options  What \Surface\Windows\StagedWindow::options() takes.
     *
     * @throws WindowException for a name already open or an option it does not take
     */
    public function openStaged(string $name, int $width, int $height, array $options = []): StagedWindow;

    public function hasStaged(string $name): bool;

    public function getStaged(string $name): ?StagedWindow;

    /** @return array<string, StagedWindow> by name */
    public function allStaged(): array;

    public function closeAllStaged(): void;

    /** @return list<Display> every display, primary first */
    public function displays(): array;

    public function primaryDisplay(): Display;
}
