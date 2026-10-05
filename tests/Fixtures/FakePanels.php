<?php

declare(strict_types=1);

use GeneralPurposeIO\Contracts\IntegratedCircuits\BootSequence;
use GeneralPurposeIO\Contracts\IntegratedCircuits\DisplayPanel;
use GeneralPurposeIO\Contracts\IntegratedCircuits\PipeablePanel;
use GeneralPurposeIO\Contracts\IntegratedCircuits\RefreshesOnCommand;
use GeneralPurposeIO\Contracts\IntegratedCircuits\RefreshMode;
use GeneralPurposeIO\Contracts\IntegratedCircuits\Switchable;
use GeneralPurposeIO\Contracts\IntegratedCircuits\WindowAddressable;
use GeneralPurposeIO\Contracts\SPI\WritesFromMemory;
use Surface\Contracts\Framebuffers\FormatSpec;

/** Records transmit(), refresh() and setDisplay() calls in order; the next call throws $fail when it is set. */
abstract class FakePanel implements DisplayPanel
{
    /** @var list<list<mixed>> */
    public array $calls = [];

    public ?Throwable $fail = null;

    public function __construct(public int $w, public int $h, public FormatSpec $format) {}

    public function width(): int
    {
        return $this->w;
    }

    public function height(): int
    {
        return $this->h;
    }

    public function formatSpec(): FormatSpec
    {
        return $this->format;
    }

    public function transmit(int $origin_x, int $origin_y, array $raw_data, ?int $frame_width = null, ?int $frame_height = null): void
    {
        $this->call('transmit', $origin_x, $origin_y, $frame_width, $frame_height, $raw_data);
    }

    protected function call(string $what, mixed ...$args): void
    {
        if (! is_null($this->fail)) {
            $failure = $this->fail;
            $this->fail = null;

            throw $failure;
        }
        $this->calls[] = [$what, ...$args];
    }

    /** @return list<array{int, int, int, int}> x, y, width, height of each transmit */
    public function windows(): array
    {
        return array_values(array_map(
            fn (array $call): array => [$call[1], $call[2], $call[3], $call[4]],
            array_filter($this->calls, fn (array $call): bool => $call[0] === 'transmit'),
        ));
    }

    /** @return list<string> the name of every call, in order */
    public function verbs(): array
    {
        return array_map(fn (array $call): string => $call[0], $this->calls);
    }
}

/** An OLED or TFT: region writes, output on and off. */
class FakeWindowPanel extends FakePanel implements WindowAddressable, Switchable
{
    public function setDisplay(bool $on): void
    {
        $this->call('display', $on);
    }
}

/** Whole frames and nothing else: an LED matrix. */
class FakeWholePanel extends FakePanel {}

/** A whole-frame ePaper panel. */
class FakeInkPanel extends FakePanel implements RefreshesOnCommand
{
    public function refresh(RefreshMode $mode = RefreshMode::FULL): void
    {
        $this->call('refresh', $mode);
    }
}

/** A region-writing ePaper panel, as the SSD1680. */
class FakeWindowInkPanel extends FakeInkPanel implements WindowAddressable {}

/** A panel that has not booted yet. */
class FakeUnbootedPanel extends FakeWindowPanel implements BootSequence
{
    public function boot(): void {}

    public function hasBooted(): bool
    {
        return false;
    }
}

/** A display panel that never says how it packs its bytes. */
class FakeFormatlessPanel implements DisplayPanel
{
    public function width(): int
    {
        return 8;
    }

    public function height(): int
    {
        return 8;
    }

    public function transmit(int $origin_x, int $origin_y, array $raw_data, ?int $frame_width = null, ?int $frame_height = null): void {}
}

/** Records every writeFrom(). */
class FakeMemoryBus implements WritesFromMemory
{
    /** @var list<list<array{int, int}>> */
    public array $spans = [];

    /** Every write answers this when set. */
    public ?int $answer = null;

    public function writeFrom(array $spans): int
    {
        $this->spans[] = $spans;

        return $this->answer ?? array_sum(array_column($spans, 1));
    }
}

/** A TFT fed from memory, as the ST7796 on spidev. */
class FakePipePanel extends FakeWindowPanel implements PipeablePanel
{
    public function __construct(int $w, int $h, FormatSpec $format, public ?FakeMemoryBus $bus = new FakeMemoryBus)
    {
        parent::__construct($w, $h, $format);
    }

    public function openWindow(int $x, int $y, int $width, int $height): void
    {
        $this->call('window', $x, $y, $width, $height);
    }

    public function pixelBus(): ?WritesFromMemory
    {
        return $this->bus;
    }
}
