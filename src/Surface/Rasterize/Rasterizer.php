<?php

namespace Surface\Rasterize;

use Surface\Contracts\Framebuffers\Framebuffer;
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
        return $this->scan($this->polylineOp($points, $stroke, $closed));
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
        return $this->scan($this->pathOp($contours, $rule));
    }

    public function fillEllipse(float $cx, float $cy, float $rx, float $ry): string
    {
        return $this->scan($this->ellipseOp($cx, $cy, $rx, $ry));
    }

    public function strokeEllipse(float $cx, float $cy, float $rx, float $ry, float $stroke = 1.0): string
    {
        return $this->scan($this->ringOp($cx, $cy, $rx, $ry, $stroke));
    }

    public function fillRoundRect(float $x, float $y, float $width, float $height, array $radii): string
    {
        return $this->scan($this->roundRectOp($x, $y, $width, $height, $radii));
    }

    public function strokeRoundRect(float $x, float $y, float $width, float $height, array $radii, float $stroke): string
    {
        return $this->scan($this->roundRingOp($x, $y, $width, $height, $radii, $stroke));
    }

    public function shadow(float $x, float $y, float $width, float $height, float $radius, float $blur): string
    {
        return $this->scan($this->shadowOp($x, $y, $width, $height, $radius, $blur));
    }

    public function fillBatch(array $shapes, Framebuffer $target, bool $direct = true): void
    {
        [$ops, $colours] = $this->lower($shapes);
        if ($ops === []) {
            return;
        }
        foreach ($this->scanner->batch($ops) as $i => $spans) {
            if ($spans !== '') {
                $target->paintSpans($spans, $colours[$i]);
            }
        }
    }

    /**
     * Shape commands as scanner ops and their colours, every shape checked; shapes that cover nothing dropped.
     *
     * @param  list<array<int, mixed>>  $shapes
     * @return array{list<list<mixed>>, list<int>}
     */
    protected function lower(array $shapes): array
    {
        [$ops, $colours] = [[], []];
        foreach ($shapes as $i => $c) {
            // lowered exactly as the methods of these kinds lower them; the box kinds carry corners, the methods sizes
            $op = match ($c[0] ?? null) {
                'path' => $this->pathOp($c[1], $c[2]),
                'polyline' => $this->polylineOp($c[1], $c[2], $c[3]),
                'ellipse' => $this->ellipseOp($c[1], $c[2], $c[3], $c[4]),
                'ring' => $this->ringOp($c[1], $c[2], $c[3], $c[4], $c[5]),
                'roundrect' => $this->roundRectOp($c[1], $c[2], $c[3] - $c[1], $c[4] - $c[2], $c[5]),
                'roundring' => $this->roundRingOp($c[1], $c[2], $c[3] - $c[1], $c[4] - $c[2], $c[5], $c[6]),
                'shadow' => $this->shadowOp($c[1], $c[2], $c[3] - $c[1], $c[4] - $c[2], $c[5], $c[6]),
                default => throw new RasterizeException("Shape {$i} is not a path, polyline, ellipse, ring, roundrect, roundring or shadow command."),
            };
            if ($op !== null) {
                $ops[] = $op;
                $colours[] = $c[self::COLOUR[$c[0]]];
            }
        }

        return [$ops, $colours];
    }

    /** Where each shape command holds its colour. */
    private const array COLOUR = ['path' => 3, 'polyline' => 4, 'ellipse' => 5, 'ring' => 6, 'roundrect' => 6, 'roundring' => 7, 'shadow' => 7];

    /** @param list<mixed>|null $op */
    private function scan(?array $op): string
    {
        return $op === null ? '' : $this->scanner->batch([$op])[0];
    }

    /** @return list<mixed>|null The scanner op the shape lowers to; null when it covers nothing. */
    private function polylineOp(array $points, float $stroke, bool $closed): ?array
    {
        self::numbers(['stroke' => $stroke]);
        $flat = self::flatten($points);
        if ($stroke <= 0 || $flat === []) {
            return null;
        }

        return $this->thin($stroke) ? [Scanner::POLYLINE, $flat, $closed] : $this->outlineOp(array_chunk($flat, 2), $stroke, $closed);
    }

    /** @return list<mixed>|null */
    private function pathOp(array $contours, FillRule $rule): ?array
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

        return $flat === [] ? null : [Scanner::PATH, $flat, $rule === FillRule::EVEN_ODD ? 1 : 0];
    }

    /** @return list<mixed>|null */
    private function ellipseOp(float $cx, float $cy, float $rx, float $ry): ?array
    {
        self::numbers(['cx' => $cx, 'cy' => $cy, 'rx' => $rx, 'ry' => $ry]);

        return $rx <= 0 || $ry <= 0 ? null : [Scanner::ELLIPSE, $cx, $cy, $rx, $ry];
    }

    /** @return list<mixed>|null */
    private function ringOp(float $cx, float $cy, float $rx, float $ry, float $stroke): ?array
    {
        self::numbers(['cx' => $cx, 'cy' => $cy, 'rx' => $rx, 'ry' => $ry, 'stroke' => $stroke]);

        return $rx <= 0 || $ry <= 0 || $stroke <= 0 ? null : [Scanner::RING, $cx, $cy, $rx, $ry, $stroke];
    }

    /** @return list<mixed>|null */
    private function roundRectOp(float $x, float $y, float $width, float $height, array $radii): ?array
    {
        self::numbers(['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height]);
        $held = self::radii($radii, $width, $height);

        return $width <= 0 || $height <= 0 ? null : [Scanner::ROUND_RECT, $x, $y, $x + $width, $y + $height, $held];
    }

    /** @return list<mixed>|null */
    private function roundRingOp(float $x, float $y, float $width, float $height, array $radii, float $stroke): ?array
    {
        self::numbers(['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height, 'stroke' => $stroke]);
        $held = self::radii($radii, $width, $height);

        return $width <= 0 || $height <= 0 || $stroke <= 0 ? null : [Scanner::ROUND_RING, $x, $y, $x + $width, $y + $height, $held, $stroke];
    }

    /** @return list<mixed>|null */
    private function shadowOp(float $x, float $y, float $width, float $height, float $radius, float $blur): ?array
    {
        self::numbers(['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height, 'radius' => $radius, 'blur' => $blur]);
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        return [Scanner::SHADOW, $x, $y, $x + $width, $y + $height, self::hold($radius, $width, $height), max(0.0, $blur)];
    }

    /**
     * Four radii, each held to 0..min(w, h)/2.
     *
     * @return array{float, float, float, float}
     */
    private static function radii(array $radii, float $width, float $height): array
    {
        if (! array_is_list($radii) || count($radii) !== 4) {
            throw new RasterizeException('Radii are a list of four numbers: [tl, tr, br, bl].');
        }
        $held = [];
        foreach ($radii as $i => $r) {
            if (! is_int($r) && ! is_float($r)) {
                throw new RasterizeException('Radii are a list of four numbers: [tl, tr, br, bl].');
            }
            self::number("Radius {$i}", (float) $r);
            $held[] = self::hold((float) $r, $width, $height);
        }

        return $held;
    }

    private static function hold(float $radius, float $width, float $height): float
    {
        return max(0.0, min($radius, min($width, $height) / 2));
    }

    /** Hard edges draw a stroke of at most one pixel as a one-pixel line. */
    private function thin(float $stroke): bool
    {
        return $this->edges === Edges::HARD && $stroke <= 1.0;
    }

    /** @param list<array{float, float}> $points */
    private function outline(array $points, float $stroke, bool $closed): string
    {
        return $this->scan($this->outlineOp($points, $stroke, $closed));
    }

    /**
     * @param  list<array{float, float}>  $points
     * @return list<mixed>|null
     */
    private function outlineOp(array $points, float $stroke, bool $closed): ?array
    {
        $contours = Stroker::outline($points, $stroke, $closed);

        return $contours === [] ? null : [Scanner::PATH, $contours, 0];
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
