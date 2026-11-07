<?php

namespace Surface\Fonts\Extended;

use FcCharSet;
use FcConfig;
use FcFontSet;
use FcPattern;
use Surface\Contracts\Fonts\FontException;

/**
 * Fonts found through fontconfig: the best match for a pattern such as 'sans-serif:weight=200', and per
 * codepoint the first font in the pattern's sorted list that has it. Matches, sorted lists and fallbacks are
 * cached, so a frame never asks fontconfig twice. fontconfig handles are not freed when PHP drops them:
 * close() releases every one the catalog holds.
 */
class FontCatalog
{
    /** How many times FcFontSort ran; read by tests. */
    public private(set) int $sorts = 0;

    private ?FcConfig $config = null;

    /** @var array<string, FontFile> */
    private array $matches = [];

    /** @var array<string, array{FcFontSet, list<array{FontFile, FcCharSet}>}> sorted fonts per pattern; the charsets belong to the set */
    private array $sorted = [];

    /** @var array<string, array{int, int}> */
    private array $requested = [];

    /** @var array<string, array<int, ?FontFile>> */
    private array $fallbacks = [];

    public function match(string $pattern): FontFile
    {
        if (isset($this->matches[$pattern])) {
            return $this->matches[$pattern];
        }
        $wanted = $this->prepared($pattern);
        $result = null;
        $found = FcFontMatch($this->config(), $wanted, $result);
        FcPatternDestroy($wanted);
        if (is_null($found)) {
            throw FontException::notFound($pattern);
        }
        $file = self::fileOf($found);
        FcPatternDestroy($found);
        if (is_null($file)) {
            throw FontException::notFound($pattern);
        }

        return $this->matches[$pattern] = $file;
    }

    /**
     * The slant and weight the pattern asks for, after fontconfig's defaults (FC_SLANT_ROMAN, FC_WEIGHT_REGULAR).
     *
     * @return array{int, int} slant, weight
     */
    public function requested(string $pattern): array
    {
        if (! isset($this->requested[$pattern])) {
            $wanted = $this->prepared($pattern);
            $slant = 0;
            $weight = 80;
            FcPatternGetInteger($wanted, FC_SLANT, 0, $slant);
            FcPatternGetInteger($wanted, FC_WEIGHT, 0, $weight);
            FcPatternDestroy($wanted);
            $this->requested[$pattern] = [(int) $slant, (int) $weight];
        }

        return $this->requested[$pattern];
    }

    /** The first font in the pattern's sorted list that has the codepoint; null when none has it. */
    public function fallback(string $pattern, int $codepoint): ?FontFile
    {
        if (array_key_exists($codepoint, $this->fallbacks[$pattern] ?? [])) {
            return $this->fallbacks[$pattern][$codepoint];
        }
        $found = null;
        foreach ($this->sortedFor($pattern) as [$file, $charset]) {
            if (FcCharSetHasChar($charset, $codepoint)) {
                $found = $file;
                break;
            }
        }

        return $this->fallbacks[$pattern][$codepoint] = $found;
    }

    /** Whether a font in any sorted list so far has the codepoint. */
    public function covers(FontFile $file, int $codepoint): bool
    {
        foreach ($this->sorted as [, $fonts]) {
            foreach ($fonts as [$candidate, $charset]) {
                if ($candidate == $file) {
                    return FcCharSetHasChar($charset, $codepoint);
                }
            }
        }

        return false;
    }

    public function close(): void
    {
        foreach ($this->sorted as [$set]) {
            FcFontSetDestroy($set);
        }
        if (! is_null($this->config)) {
            FcConfigDestroy($this->config);
        }
        $this->sorted = [];
        $this->matches = [];
        $this->fallbacks = [];
        $this->requested = [];
        $this->config = null;
    }

    /** @return list<array{FontFile, FcCharSet}> */
    private function sortedFor(string $pattern): array
    {
        if (! isset($this->sorted[$pattern])) {
            $wanted = $this->prepared($pattern);
            $coverage = null;
            $result = null;
            $set = FcFontSort($this->config(), $wanted, true, $coverage, $result);
            $this->sorts++;
            FcPatternDestroy($wanted);
            if (! is_null($coverage)) {
                FcCharSetDestroy($coverage);
            }
            $fonts = [];
            foreach ($set?->fonts ?? [] as $font) {
                $charset = null;
                $file = self::fileOf($font);
                // A font that claims the last private-use codepoint is a last-resort font (macOS LastResort): its
                // boxes stand in for every codepoint, so it would hide the primary face's .notdef.
                if (! is_null($file) && FcPatternGetCharSet($font, FC_CHARSET, 0, $charset) === FcResultMatch && ! is_null($charset)
                    && ! FcCharSetHasChar($charset, 0x10FFFD)) {
                    $fonts[] = [$file, $charset];
                }
            }
            if (is_null($set)) {
                return $fonts;
            }
            $this->sorted[$pattern] = [$set, $fonts];
        }

        return $this->sorted[$pattern][1];
    }

    /** A parsed pattern with config and default substitutions applied; the caller destroys it. */
    private function prepared(string $pattern): FcPattern
    {
        $parsed = FcNameParse($pattern) ?? throw FontException::notFound($pattern);
        FcConfigSubstitute($this->config(), $parsed, FcMatchPattern);
        FcDefaultSubstitute($parsed);

        return $parsed;
    }

    private function config(): FcConfig
    {
        return $this->config ??= FcInitLoadConfigAndFonts() ?? throw FontException::library('fontconfig could not load its configuration');
    }

    private static function fileOf(FcPattern $font): ?FontFile
    {
        $path = null;
        $index = 0;
        if (FcPatternGetString($font, FC_FILE, 0, $path) !== FcResultMatch || ! is_string($path)) {
            return null;
        }
        FcPatternGetInteger($font, FC_INDEX, 0, $index);

        return new FontFile($path, (int) $index);
    }
}
