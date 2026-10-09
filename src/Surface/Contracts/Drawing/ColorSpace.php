<?php

namespace Surface\Contracts\Drawing;

/**
 * How a target's values map to light. The extended linear spaces put SDR
 * white at 1.0 and go past it (scRGB, macOS EDR); Hdr10Pq is BT.2020
 * primaries with the SMPTE ST 2084 (PQ) transfer.
 */
enum ColorSpace: string
{
    case Srgb = 'srgb';
    case DisplayP3 = 'display-p3';
    case ExtendedLinearSrgb = 'extended-linear-srgb';
    case ExtendedLinearDisplayP3 = 'extended-linear-display-p3';
    case Hdr10Pq = 'hdr10-pq';

    /** Whether values in it reach past SDR white. */
    public function isHdr(): bool
    {
        return in_array($this, [self::ExtendedLinearSrgb, self::ExtendedLinearDisplayP3, self::Hdr10Pq], true);
    }
}
