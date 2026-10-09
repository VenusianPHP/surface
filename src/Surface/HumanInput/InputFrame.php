<?php

namespace Surface\HumanInput;

use Closure;

/**
 * Where one frame of input ends. The loop turns many times per sketch frame (every
 * toolkit wake is a turn), so a poll on every turn cannot clear edges: a tap between
 * two frames would be gone before the frame that should read it. Devices tell the frame
 * when their state is read; a poll clears edges only once the frame was read since the
 * last clear, or once no frame has read input for two frame lengths, so a sketch that
 * stops reading for a while gets no stale presses when it reads again. Polls in between
 * add up. The first read after a poll runs the catch-up pass, so what the toolkit
 * dispatched since that poll is in the state the read returns.
 */
final class InputFrame
{
    private bool $read = false;

    private bool $applying = false;

    /** When the last frame that read input was ended. */
    private int $closed_ns;

    /** @var Closure(): void|null */
    private ?Closure $catch_up = null;

    /**
     * @param Closure(): int $length_ns one frame's length, asked each time so a changed refresh rate applies at once
     * @param int|null $started_ns the clock reading the frame starts from; now by default
     */
    public function __construct(private readonly Closure $length_ns, ?int $started_ns = null)
    {
        $this->closed_ns = $started_ns ?? hrtime(true);
    }

    /**
     * What the first read of a frame runs: apply what arrived since the last poll, clearing nothing.
     *
     * @param Closure(): void|null $catch_up
     * @return void
     */
    public function catchUpWith(?Closure $catch_up): void
    {
        $this->catch_up = $catch_up;
    }

    /**
     * A device's state was read. The first read after a frame ends runs the catch-up,
     * and only a read whose catch-up finished counts, so a catch-up that throws (a
     * toolkit with no engine) throws again on every read until it is put right; reads
     * made while input is applied count for nothing.
     *
     * @return void
     */
    public function read(): void
    {
        if ($this->read || $this->applying) {
            return;
        }

        if (! is_null($this->catch_up)) {
            $this->applying($this->catch_up);
        }

        $this->read = true;
    }

    /**
     * Whether the edges the devices hold are spent: a device was read since the last
     * call (which ends that frame), or no frame has read input for two frame lengths.
     *
     * @param int|null $now_ns the clock reading to judge by; now by default
     * @return bool
     */
    public function spent(?int $now_ns = null): bool
    {
        $now_ns ??= hrtime(true);

        if ($this->read) {
            $this->read = false;
            $this->closed_ns = $now_ns;

            return true;
        }

        return $now_ns - $this->closed_ns >= 2 * ($this->length_ns)();
    }

    /**
     * Run $work as input being applied: reads it makes do not count as a frame's reads.
     *
     * @param Closure $work
     * @return void
     */
    public function applying(Closure $work): void
    {
        $was = $this->applying;
        $this->applying = true;

        try {
            $work();
        } finally {
            $this->applying = $was;
        }
    }
}
