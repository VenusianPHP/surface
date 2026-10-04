<?php

namespace Surface\Rasterize;

use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\FillRule;
use Surface\Contracts\Rasterize\RasterizeException;
use Surface\Contracts\Rasterize\Rasterizer as RasterizerContract;
use Surface\Contracts\Rasterize\Scanner;

/**
 * Shapes, lowered once for every driver: input checked, thin hard lines sent
 * to the scanner's one-pixel polyline, everything else to paths, ellipses and
 * rings. A flavor only says where its scanner comes from (mint), so both
 * drivers see exactly the same lowered geometry.
 */
abstract class Rasterizer implements RasterizerContract
{
    protected Scanner $scanner;

    public function __construct(
        protected readonly Region $clip,
        protected readonly Edges $edges,
    ) {
        if ($clip->isEmpty() || $clip->x < 0 || $clip->y < 0 || $clip->right() > 0xFFFF || $clip->bottom() > 0xFFFF) {
            throw RasterizeException::clip($clip);
        }
        $this->scanner = $this->mint($clip, $edges);
    }

    abstract protected function mint(Region $clip, Edges $edges): Scanner;

    public function edges(): Edges
    {
        return $this->edges;
    }

    public function clip(): Region
    {
        return $this->clip;
    }

    public function fillRect(float $x, float $y, float $width, float $height): string
    {
        self::numbers(['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height]);
        if ($width <= 0 || $height <= 0) {
            return '';
        }

        return $this->scanner->path([self::rect($x, $y, $width, $height)], FillRule::NON_ZERO);
    }

    public function strokeRect(float $x, float $y, float $width, float $height, float $stroke = 1.0): string
    {
        self::numbers(['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height, 'stroke' => $stroke]);
        if ($width <= 0 || $height <= 0 || $stroke <= 0) {
            return '';
        }
        if ($this->thin($stroke)) {
            return $this->scanner->polyline([$x, $y, $x + $width, $y, $x + $width, $y + $height, $x, $y + $height], true);
        }

        $half = $stroke / 2;
        $contours = [self::rect($x - $half, $y - $half, $width + $stroke, $height + $stroke)];
        if ($width > $stroke && $height > $stroke) {
            $contours[] = self::rect($x + $half, $y + $half, $width - $stroke, $height - $stroke);
        }

        return $this->scanner->path($contours, FillRule::EVEN_ODD);
    }

    public function line(float $x0, float $y0, float $x1, float $y1, float $stroke = 1.0): string
    {
        self::numbers(['x0' => $x0, 'y0' => $y0, 'x1' => $x1, 'y1' => $y1, 'stroke' => $stroke]);
        if ($stroke <= 0) {
            return '';
        }

        return $this->thin($stroke)
            ? $this->scanner->polyline([$x0, $y0, $x1, $y1], false)
            : $this->outline([[$x0, $y0], [$x1, $y1]], $stroke, false);
    }

    public function polyline(array $points, float $stroke = 1.0, bool $closed = false): string
    {
        self::numbers(['stroke' => $stroke]);
        $flat = self::flatten($points);
        if ($stroke <= 0 || $flat === []) {
            return '';
        }

        return $this->thin($stroke)
            ? $this->scanner->polyline($flat, $closed)
            : $this->outline(array_chunk($flat, 2), $stroke, $closed);
    }

    public function fillTriangle(float $x0, float $y0, float $x1, float $y1, float $x2, float $y2): string
    {
        self::numbers(['x0' => $x0, 'y0' => $y0, 'x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2]);

        return $this->scanner->path([[$x0, $y0, $x1, $y1, $x2, $y2]], FillRule::NON_ZERO);
    }

    public function fillPolygon(array $points, FillRule $rule = FillRule::NON_ZERO): string
    {
        $flat = self::flatten($points);

        return count($flat) < 6 ? '' : $this->scanner->path([$flat], $rule);
    }

    public function fillPath(array $contours, FillRule $rule = FillRule::NON_ZERO): string
    {
        $flat = [];
        foreach (array_values($contours) as $position => $contour) {
            if (! is_array($contour) || ! array_is_list($contour)) {
                throw new RasterizeException("Contour {$position} is not a list of points.");
            }
            $points = self::flatten($contour);
            if (count($points) >= 6) {
                $flat[] = $points;
            }
        }

        return $flat === [] ? '' : $this->scanner->path($flat, $rule);
    }

    public function fillEllipse(float $cx, float $cy, float $rx, float $ry): string
    {
        self::numbers(['cx' => $cx, 'cy' => $cy, 'rx' => $rx, 'ry' => $ry]);

        return $rx <= 0 || $ry <= 0 ? '' : $this->scanner->ellipse($cx, $cy, $rx, $ry);
    }

    public function strokeEllipse(float $cx, float $cy, float $rx, float $ry, float $stroke = 1.0): string
    {
        self::numbers(['cx' => $cx, 'cy' => $cy, 'rx' => $rx, 'ry' => $ry, 'stroke' => $stroke]);

        return $rx <= 0 || $ry <= 0 || $stroke <= 0 ? '' : $this->scanner->ring($cx, $cy, $rx, $ry, $stroke);
    }

    /** Hard edges draw a stroke of at most one pixel as a one-pixel line. */
    private function thin(float $stroke): bool
    {
        return $this->edges === Edges::HARD && $stroke <= 1.0;
    }

    /** @param list<array{float, float}> $points */
    private function outline(array $points, float $stroke, bool $closed): string
    {
        $contours = Stroker::outline($points, $stroke, $closed);

        return $contours === [] ? '' : $this->scanner->path($contours, FillRule::NON_ZERO);
    }

    /** @return list<float> */
    private static function rect(float $x, float $y, float $width, float $height): array
    {
        return [$x, $y, $x + $width, $y, $x + $width, $y + $height, $x, $y + $height];
    }

    /** @param array<string, float> $numbers */
    private static function numbers(array $numbers): void
    {
        foreach ($numbers as $what => $value) {
            self::number($what, $value);
        }
    }

    private static function number(string $what, float $value): void
    {
        if (! is_finite($value)) {
            throw RasterizeException::notFinite($what);
        }
        if (abs($value) > self::LIMIT) {
            throw RasterizeException::pastLimit($what, $value, self::LIMIT);
        }
    }

    /**
     * [x, y] points as one flat list, each checked.
     *
     * @return list<float>
     */
    private static function flatten(array $points): array
    {
        $flat = [];
        foreach (array_values($points) as $position => $point) {
            if (! is_array($point) || ! array_is_list($point) || count($point) !== 2
                || ! (is_int($point[0]) || is_float($point[0])) || ! (is_int($point[1]) || is_float($point[1]))) {
                throw RasterizeException::notAPoint($position);
            }
            self::number("Point {$position} x", (float) $point[0]);
            self::number("Point {$position} y", (float) $point[1]);
            $flat[] = (float) $point[0];
            $flat[] = (float) $point[1];
        }

        return $flat;
    }
}
