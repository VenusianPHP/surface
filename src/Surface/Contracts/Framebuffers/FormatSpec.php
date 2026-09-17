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
    ) {}
}