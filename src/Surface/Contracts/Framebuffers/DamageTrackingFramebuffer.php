<?php

namespace Surface\Contracts\Framebuffers;

/** The dirty buffer: every write is recorded; beginEpoch() starts a fresh record. */
interface DamageTrackingFramebuffer extends Framebuffer
{
    public function beginEpoch(): void;

    /** Regions written since beginEpoch(), merged and snapped to damageGranularity(). @return list<Region> */
    public function damage(): array;
}
