<?php

namespace Surface\Contracts\Framebuffers;

class FormatSpec
{
    public function __construct(
        public PixelFormat $pixel_format,
        public BitDepth $bit_depth,
        public ScanDirection $scan_direction = ScanDirection::TOP_TO_BOTTOM,
        public ?BitOrder $bit_order = null,
        public ?Endianness $endianness = null,
        public ?PageAxis $page_axis = null,
        public ?ChannelPalette $palette = null,
        public ?ChannelOrder $channel_order = null,
    ) {}

    /** R G B A, one byte each: what graphics libraries and image files exchange. */
    public static function rgba8(): self
    {
        return new self(PixelFormat::ROW_MAJOR, BitDepth::B32, channel_order: ChannelOrder::RGBA);
    }

    /** B G R A, one byte each: what window systems take as 32-bit ARGB on a little-endian host. */
    public static function bgra8(): self
    {
        return new self(PixelFormat::ROW_MAJOR, BitDepth::B32, channel_order: ChannelOrder::BGRA);
    }

    public function equals(FormatSpec $other): bool
    {
        if ($this->pixel_format !== $other->pixel_format
            || $this->bit_depth !== $other->bit_depth
            || $this->scan_direction !== $other->scan_direction
            || $this->bit_order !== $other->bit_order
            || $this->endianness !== $other->endianness
            || $this->page_axis !== $other->page_axis
            || $this->channel_order !== $other->channel_order) {
            return false;
        }

        if (is_null($this->palette) || is_null($other->palette)) {
            return is_null($this->palette) && is_null($other->palette);
        }

        return $this->palette->equals($other->palette);
    }
}
