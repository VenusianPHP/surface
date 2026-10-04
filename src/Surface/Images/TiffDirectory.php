<?php

namespace Surface\Images;

use Surface\Contracts\Images\ImageException;
use Surface\Contracts\Images\ImageFormat;

/** The first image file directory of a classic TIFF: its tags, read on demand and bounds-checked. */
final class TiffDirectory
{
    /**
     * @param  array<int, array{int, int, int}>  $entries  tag => [type, count, offset of the 4-byte value field]
     */
    private function __construct(
        private readonly string $bytes,
        public readonly bool $bigEndian,
        private readonly array $entries,
    ) {}

    /** @throws ImageException When the header or directory is cut short or malformed. */
    public static function read(string $bytes): self
    {
        if (strlen($bytes) < 8) {
            throw self::corrupt('its header is cut short.');
        }
        $big = $bytes[0] === 'M';
        $at = self::unsigned($bytes, 4, 4, $big);
        if ($at < 8 || $at + 2 > strlen($bytes)) {
            throw self::corrupt('its first directory lies outside the file.');
        }
        $count = self::unsigned($bytes, $at, 2, $big);
        if ($at + 2 + 12 * $count > strlen($bytes)) {
            throw self::corrupt('its first directory is cut short.');
        }

        $entries = [];
        for ($i = 0; $i < $count; $i++) {
            $entry = $at + 2 + 12 * $i;
            $entries[self::unsigned($bytes, $entry, 2, $big)] = [self::unsigned($bytes, $entry + 2, 2, $big), self::unsigned($bytes, $entry + 4, 4, $big), $entry + 8];
        }

        return new self($bytes, $big, $entries);
    }

    public function has(int $tag): bool
    {
        return isset($this->entries[$tag]);
    }

    /** The tag's first value, or $default when the tag is absent. */
    public function number(int $tag, ?int $default = null): int
    {
        if (! $this->has($tag)) {
            return $default ?? throw self::corrupt("it lacks tag {$tag}.");
        }

        return $this->numbers($tag)[0] ?? throw self::corrupt("tag {$tag} holds no value.");
    }

    /**
     * Every value of a BYTE, SHORT or LONG tag; $default when the tag is absent.
     *
     * @param  list<int>|null  $default
     * @return list<int>
     */
    public function numbers(int $tag, ?array $default = null): array
    {
        if (! $this->has($tag)) {
            return $default ?? throw self::corrupt("it lacks tag {$tag}.");
        }

        [$type, $count, $field] = $this->entries[$tag];
        $size = match ($type) {
            1 => 1,
            3 => 2,
            4 => 4,
            default => throw self::corrupt("tag {$tag} has field type {$type} where a whole number belongs."),
        };
        $at = $count * $size <= 4 ? $field : self::unsigned($this->bytes, $field, 4, $this->bigEndian);
        if ($at + $count * $size > strlen($this->bytes)) {
            throw self::corrupt("tag {$tag}'s values lie outside the file.");
        }

        $values = [];
        for ($i = 0; $i < $count; $i++) {
            $values[] = self::unsigned($this->bytes, $at + $i * $size, $size, $this->bigEndian);
        }

        return $values;
    }

    public static function unsigned(string $bytes, int $at, int $size, bool $big): int
    {
        return unpack(match ($size) {
            1 => 'C',
            2 => $big ? 'n' : 'v',
            4 => $big ? 'N' : 'V',
        }, $bytes, $at)[1];
    }

    public static function corrupt(string $reason): ImageException
    {
        return ImageException::corrupt(ImageFormat::TIFF, $reason);
    }
}
