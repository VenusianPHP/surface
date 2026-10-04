<?php

declare(strict_types=1);

namespace Venusian\Surface\Tests\Support\Framebuffers;

use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\BitOrder;
use Surface\Contracts\Framebuffers\ChannelOrder;
use Surface\Contracts\Framebuffers\ChannelPalette;
use Surface\Contracts\Framebuffers\ChannelSpec;
use Surface\Contracts\Framebuffers\EInkColor;
use Surface\Contracts\Framebuffers\Endianness;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\PageAxis;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\ScanDirection;

/** Every storable layout in every order it takes: what the parity tests sweep. */
final class Formats
{
    public static function mono(): FormatSpec
    {
        return new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1);
    }

    public static function rgb565(): FormatSpec
    {
        return new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B16);
    }

    /** Black/white/red in two planes; the black plane is inverted (SSD1680). */
    public static function planarBwr(): FormatSpec
    {
        return new FormatSpec(PixelFormat::PLANAR, BitDepth::B1, palette: new ChannelPalette(
            new ChannelSpec(EInkColor::BLACK->value, true),
            new ChannelSpec(EInkColor::RED->value),
        ));
    }

    /** Spectra 6 as the controller takes it: four bits per pixel, the panel's own codes. */
    public static function spectra6(): FormatSpec
    {
        return new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B4, palette: new ChannelPalette(
            new ChannelSpec(EInkColor::BLACK->value, code: 0),
            new ChannelSpec(EInkColor::WHITE->value, code: 1),
            new ChannelSpec(EInkColor::YELLOW->value, code: 2),
            new ChannelSpec(EInkColor::RED->value, code: 3),
            new ChannelSpec(EInkColor::BLUE->value, code: 5),
            new ChannelSpec(EInkColor::GREEN->value, code: 6),
        ));
    }

    /** @return array<string, FormatSpec> */
    public static function all(): array
    {
        $row = PixelFormat::ROW_MAJOR;
        $formats = [
            'mono rows msb' => self::mono(),
            'mono rows lsb' => new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1, bit_order: BitOrder::LSB_FIRST),
            'mono rows bottom-up' => new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1, ScanDirection::BOTTOM_TO_TOP),
            'mono pages' => new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1),
            'mono pages msb' => new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1, bit_order: BitOrder::MSB_FIRST),
            'mono pages, horizontal axis' => new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1, page_axis: PageAxis::HORIZONTAL),
            'grey 2' => new FormatSpec($row, BitDepth::B2),
            'grey 4 lsb' => new FormatSpec($row, BitDepth::B4, bit_order: BitOrder::LSB_FIRST),
            'grey 8' => new FormatSpec($row, BitDepth::B8),
            'index 2 bwr' => new FormatSpec($row, BitDepth::B2, palette: new ChannelPalette(
                new ChannelSpec(EInkColor::BLACK->value, code: 0),
                new ChannelSpec(EInkColor::WHITE->value, code: 1),
                new ChannelSpec(EInkColor::RED->value, code: 3),
            )),
            'index 4 spectra 6' => self::spectra6(),
            'planar bwr' => self::planarBwr(),
            'rgb565' => self::rgb565(),
            'rgb565 lsb' => new FormatSpec($row, BitDepth::B16, endianness: Endianness::LSB),
            'rgb888 bottom-up' => new FormatSpec($row, BitDepth::B24, ScanDirection::BOTTOM_TO_TOP),
        ];

        foreach ([BitDepth::B12, BitDepth::B16, BitDepth::B18, BitDepth::B24] as $depth) {
            foreach ([ChannelOrder::RGB, ChannelOrder::BGR] as $order) {
                $formats["{$depth->value}-bit {$order->name}"] = new FormatSpec($row, $depth, channel_order: $order);
            }
        }
        foreach ([ChannelOrder::RGBA, ChannelOrder::BGRA, ChannelOrder::ARGB, ChannelOrder::ABGR] as $order) {
            $formats["32-bit {$order->name}"] = new FormatSpec($row, BitDepth::B32, channel_order: $order);
        }

        return $formats;
    }

    /** @return array<string, array{FormatSpec}> A Pest dataset. */
    public static function dataset(): array
    {
        return array_map(fn (FormatSpec $spec): array => [$spec], self::all());
    }
}
