<?php

namespace Surface\Contracts\Drawing\Effects;

use Surface\Contracts\Drawing\DrawingException;

/**
 * SVG's feComponentTransfer with type="table" on 8-bit values, CoreImage's CIColorCurves by a table: each channel of the
 * straight colour goes through a table of 256 whole numbers, 0..255, indexed by the channel's value. A channel with no
 * table (null) is left as it is. The pixel is premultiplied again by the new alpha.
 */
final readonly class Table extends FilterStep
{
    /**
     * @param  list<int>|null  $red
     * @param  list<int>|null  $green
     * @param  list<int>|null  $blue
     * @param  list<int>|null  $alpha
     *
     * @throws DrawingException When a table is not 256 whole numbers of 0 to 255.
     */
    public function __construct(
        public ?array $red = null,
        public ?array $green = null,
        public ?array $blue = null,
        public ?array $alpha = null,
        public ?string $in = null,
        public ?string $result = null,
    ) {
        foreach ([$red, $green, $blue, $alpha] as $table) {
            if ($table === null) {
                continue;
            }
            if (count($table) !== 256 || ! array_is_list($table)) {
                throw new DrawingException('A channel table is 256 numbers.');
            }
            foreach ($table as $v) {
                if (! is_int($v) || $v < 0 || $v > 255) {
                    throw new DrawingException('A channel table takes whole numbers of 0 to 255.');
                }
            }
        }
    }

    /** One table for red, green and blue; alpha as it is. @param callable(float): float $f  maps 0..1 to 0..1 */
    public static function rgb(callable $f, ?string $in = null, ?string $result = null): self
    {
        $table = [];
        for ($i = 0; $i < 256; $i++) {
            $table[] = (int) max(0, min(255, floor($f($i / 255) * 255 + 0.5)));
        }

        return new self($table, $table, $table, null, $in, $result);
    }

    public function reads(): array
    {
        return [$this->in ?? ''];
    }

    public function named(): ?string
    {
        return $this->result;
    }
}
