<?php

namespace Surface\Fonts\Support;

use Surface\Contracts\Fonts\FontException;

/**
 * Parse an Adafruit GFXfont C header (Fonts/*.h) into PHP arrays.
 */
final class AdafruitGfxHeader
{
    /**
     * @return array{
     *     first: int,
     *     last: int,
     *     yAdvance: int,
     *     bitmaps: list<int>,
     *     glyphs: list<array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, comment: string}>
     * }
     */
    public static function parse(string $source): array
    {
        if ($source === '') {
            throw FontException::invalidHeader('Adafruit GFX header source is empty.');
        }

        if (! preg_match('/const\s+uint8_t\s+\w+Bitmaps\s*\[\s*\]\s*PROGMEM\s*=\s*\{(.*?)\};/s', $source, $bitmapMatch)) {
            throw FontException::invalidHeader('Could not locate Bitmaps[] in Adafruit GFX header.');
        }

        if (! preg_match('/const\s+GFXglyph\s+\w+Glyphs\s*\[\s*\]\s*PROGMEM\s*=\s*\{(.*?)\};/s', $source, $glyphMatch)) {
            throw FontException::invalidHeader('Could not locate Glyphs[] in Adafruit GFX header.');
        }

        if (! preg_match(
            '/const\s+GFXfont\s+\w+\s+PROGMEM\s*=\s*\{[^;]*?,\s*(0x[0-9A-Fa-f]+|\d+)\s*,\s*(0x[0-9A-Fa-f]+|\d+)\s*,\s*(\d+)\s*\}\s*;/s',
            $source,
            $metaMatch,
        )) {
            throw FontException::invalidHeader('Could not locate GFXfont meta (first/last/yAdvance) in Adafruit GFX header.');
        }

        $bitmaps = self::parseHexBytes($bitmapMatch[1]);
        $glyphs = self::parseGlyphs($glyphMatch[1]);

        return [
            'first' => self::parseIntToken($metaMatch[1]),
            'last' => self::parseIntToken($metaMatch[2]),
            'yAdvance' => (int) $metaMatch[3],
            'bitmaps' => $bitmaps,
            'glyphs' => $glyphs,
        ];
    }

    public static function parseFile(string $path): array
    {
        if (! is_file($path)) {
            throw FontException::invalidHeader("Adafruit GFX header not found: {$path}");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw FontException::invalidHeader("Unable to read Adafruit GFX header: {$path}");
        }

        return self::parse($contents);
    }

    /**
     * Render a PHP class body fragment for bitmaps + glyphs properties.
     *
     * @param  array{
     *     first: int,
     *     last: int,
     *     yAdvance: int,
     *     bitmaps: list<int>,
     *     glyphs: list<array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, comment: string}>
     * }  $parsed
     */
    public static function renderClassSource(
        string $namespace,
        string $class,
        array $parsed,
    ): string {
        $bitmapLines = self::formatBitmapLines($parsed['bitmaps']);
        $glyphLines = self::formatGlyphLines($parsed['glyphs'], $parsed['first']);

        $first = sprintf('0x%02X', $parsed['first']);
        $last = sprintf('0x%02X', $parsed['last']);
        $yAdvance = (int) $parsed['yAdvance'];

        return <<<PHP
<?php

namespace {$namespace};

use Surface\\Contracts\\Fonts\\GFXFont;

class {$class} extends GFXFont
{
    protected int \$first = {$first};

    protected int \$last = {$last};

    protected int \$y_advance = {$yAdvance};

    protected array \$bitmaps = [
{$bitmapLines}
    ];

    protected array \$glyphs = [
{$glyphLines}
    ];
}

PHP;
    }

    /**
     * @return list<int>
     */
    private static function parseHexBytes(string $body): array
    {
        preg_match_all('/0x[0-9A-Fa-f]{1,2}/', $body, $matches);

        return array_map(static fn (string $token): int => hexdec($token), $matches[0]);
    }

    /**
     * @return list<array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, comment: string}>
     */
    private static function parseGlyphs(string $body): array
    {
        $glyphs = [];

        if (! preg_match_all(
            '/\{\s*(-?\d+)\s*,\s*(-?\d+)\s*,\s*(-?\d+)\s*,\s*(-?\d+)\s*,\s*(-?\d+)\s*,\s*(-?\d+)\s*\}\s*(?:,)?\s*(?:\/\/\s*(.*))?/m',
            $body,
            $matches,
            PREG_SET_ORDER,
        )) {
            throw FontException::invalidHeader('Could not parse GFXglyph entries in Adafruit GFX header.');
        }

        foreach ($matches as $match) {
            $glyphs[] = [
                (int) $match[1],
                (int) $match[2],
                (int) $match[3],
                (int) $match[4],
                (int) $match[5],
                (int) $match[6],
                'comment' => trim($match[7] ?? ''),
            ];
        }

        return $glyphs;
    }

    private static function parseIntToken(string $token): int
    {
        $token = trim($token);

        if (str_starts_with(strtolower($token), '0x')) {
            return (int) hexdec($token);
        }

        return (int) $token;
    }

    /**
     * @param  list<int>  $bitmaps
     */
    private static function formatBitmapLines(array $bitmaps): string
    {
        $chunks = array_chunk($bitmaps, 12);
        $lines = [];

        foreach ($chunks as $chunk) {
            $hex = array_map(static fn (int $b): string => sprintf('0x%02X', $b), $chunk);
            $lines[] = '        '.implode(', ', $hex).',';
        }

        if ($lines !== []) {
            $lines[array_key_last($lines)] = rtrim($lines[array_key_last($lines)], ',');
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, comment: string}>  $glyphs
     */
    private static function formatGlyphLines(array $glyphs, int $first): string
    {
        $lines = [];
        $code = $first;

        foreach ($glyphs as $glyph) {
            $comment = $glyph['comment'] !== ''
                ? $glyph['comment']
                : sprintf('0x%02X %s', $code, self::printableChar($code));

            $lines[] = sprintf(
                '        [%d, %d, %d, %d, %d, %d],  // %s',
                $glyph[0],
                $glyph[1],
                $glyph[2],
                $glyph[3],
                $glyph[4],
                $glyph[5],
                $comment,
            );
            $code++;
        }

        return implode("\n", $lines);
    }

    private static function printableChar(int $code): string
    {
        if ($code >= 0x20 && $code <= 0x7E) {
            $char = chr($code);

            if ($char === "'") {
                return "'''";
            }

            return "'{$char}'";
        }

        return '';
    }
}
