<?php

namespace Surface\Windows\Primitives;

/**
 * A text area's value split into lines where the toolkits end them: "\r\n", "\r", "\n" and
 * U+2029. Carries the UTF-16 offsets AppKit and Qt count text in. Code points are what the
 * contract speaks; UTF-16 is what NSRange and QTextCursor speak.
 */
final class TextLines
{
    /** @var list<string> */
    private readonly array $lines;

    /** @var list<int> UTF-16 units of each line's end (0 for the last line) */
    private readonly array $ends;

    /** @var list<int>|null UTF-16 offset of each line's first unit, built on first use */
    private ?array $starts = null;

    public function __construct(string $text)
    {
        $parts = preg_split('/(\r\n|\r|\n|\xE2\x80\xA9)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $lines = $ends = [];
        for ($i = 0, $n = count($parts); $i < $n; $i += 2) {
            $lines[] = $parts[$i];
            $ends[] = isset($parts[$i + 1]) ? ($parts[$i + 1] === "\r\n" ? 2 : 1) : 0;
        }
        [$this->lines, $this->ends] = [$lines, $ends];
    }

    public function count(): int
    {
        return count($this->lines);
    }

    /** Code points on the line, its line end excluded. */
    public function length(int $line): int
    {
        return mb_strlen($this->lines[$line], 'UTF-8');
    }

    public function utf16Length(int $line): int
    {
        return self::units($this->lines[$line]);
    }

    public function utf16Start(int $line): int
    {
        return $this->starts()[$line];
    }

    /** UTF-16 units of the line's first $codePoints code points. */
    public function utf16Column(int $line, int $codePoints): int
    {
        return self::units(mb_substr($this->lines[$line], 0, $codePoints, 'UTF-8'));
    }

    public function utf16Total(): int
    {
        $last = count($this->lines) - 1;

        return $this->starts()[$last] + $this->utf16Length($last);
    }

    /** The line holding a UTF-16 offset; a line's end belongs to it, offsets past the end to the last line. */
    public function lineAtUtf16(int $offset): int
    {
        $starts = $this->starts();
        [$low, $high] = [0, count($starts) - 1];
        while ($low < $high) {
            $middle = intdiv($low + $high + 1, 2);
            if ($starts[$middle] <= $offset) {
                $low = $middle;
            } else {
                $high = $middle - 1;
            }
        }

        return $low;
    }

    /**
     * @return list<int>
     */
    private function starts(): array
    {
        if (is_null($this->starts)) {
            $this->starts = [];
            $at = 0;
            foreach ($this->lines as $index => $line) {
                $this->starts[] = $at;
                $at += self::units($line) + $this->ends[$index];
            }
        }

        return $this->starts;
    }

    private static function units(string $utf8): int
    {
        return intdiv(strlen(mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8')), 2);
    }
}
