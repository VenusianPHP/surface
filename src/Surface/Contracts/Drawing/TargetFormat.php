<?php

namespace Surface\Contracts\Drawing;

/** The pixel format of a GPU engine's target. */
enum TargetFormat: string
{
    /** 8 bits a channel: SDR. */
    case Rgba8 = 'rgba8';

    /** 10 bits a colour channel and 2 of alpha: SDR in finer steps, or HDR10. */
    case Rgb10A2 = 'rgb10a2';

    /** A half float a channel: values past 1.0 are brighter than SDR white. */
    case Rgba16Float = 'rgba16float';

    /**
     * The colour spaces a target of this format carries, the default first.
     *
     * @return list<ColorSpace>
     */
    public function colorSpaces(): array
    {
        return match ($this) {
            self::Rgba8 => [ColorSpace::Srgb, ColorSpace::DisplayP3],
            self::Rgb10A2 => [ColorSpace::Srgb, ColorSpace::DisplayP3, ColorSpace::Hdr10Pq],
            self::Rgba16Float => [ColorSpace::ExtendedLinearSrgb, ColorSpace::ExtendedLinearDisplayP3],
        };
    }
}
