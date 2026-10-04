<?php

namespace Surface\Contracts\Images;

/** The encoded formats Images reads, told apart by their leading bytes. */
enum ImageFormat: string
{
    case PNG = 'png';
    case JPEG = 'jpeg';
    case TIFF = 'tiff';

    /** The format whose signature $bytes starts with; null when it is none of them. */
    public static function sniff(string $bytes): ?self
    {
        return match (true) {
            str_starts_with($bytes, "\x89PNG\r\n\x1a\n") => self::PNG,
            str_starts_with($bytes, "\xff\xd8\xff") => self::JPEG,
            str_starts_with($bytes, "II*\0"), str_starts_with($bytes, "MM\0*") => self::TIFF,
            default => null,
        };
    }
}
