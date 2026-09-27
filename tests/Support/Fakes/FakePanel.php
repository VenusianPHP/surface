<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use GeneralPurposeIO\Contracts\IntegratedCircuits\BootSequence;
use GeneralPurposeIO\Contracts\IntegratedCircuits\DisplayPanel;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FormatSpecification;

/** A display panel with no bus: records every write in order. Set $fail to make the next write throw. */
abstract class FakePanel implements BootSequence, DisplayPanel, FormatSpecification
{
    /** @var list<array{int, int, list<int>, ?int, ?int}> */
    public array $transmits = [];

    /** @var list<string> every panel call, in order */
    public array $log = [];

    public bool $booted = true;

    public ?\Throwable $fail = null;

    public function __construct(private int $width, private int $height, private FormatSpec $spec) {}

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function formatSpec(): FormatSpec
    {
        return $this->spec;
    }

    public function generateFormatSpec(): FormatSpec
    {
        return $this->spec;
    }

    public function setFormatSpec(FormatSpec $format_spec): void
    {
        $this->spec = $format_spec;
    }

    public function boot(): void
    {
        $this->booted = true;
    }

    public function hasBooted(): bool
    {
        return $this->booted;
    }

    public function transmit(int $origin_x, int $origin_y, array $raw_data, ?int $frame_width = null, ?int $frame_height = null): void
    {
        $this->failIfAsked();
        $this->log[] = 'transmit';
        $this->transmits[] = [$origin_x, $origin_y, $raw_data, $frame_width, $frame_height];
    }

    protected function failIfAsked(): void
    {
        if (! is_null($this->fail)) {
            throw $this->fail;
        }
    }
}
