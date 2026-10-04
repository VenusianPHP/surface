<?php

declare(strict_types=1);

namespace Venusian\Surface\Tests\Support\Framebuffers;

use LogicException;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\BitOrder;
use Surface\Contracts\Framebuffers\ChannelOrder;
use Surface\Contracts\Framebuffers\ChannelPalette;
use Surface\Contracts\Framebuffers\ChannelSpec;
use Surface\Contracts\Framebuffers\DamageTrackingFramebuffer;
use Surface\Contracts\Framebuffers\EInkColor;
use Surface\Contracts\Framebuffers\Endianness;
use Surface\Contracts\Framebuffers\ePaperFramebuffer;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\PageAxis;
use Surface\Contracts\Framebuffers\PagedFramebuffer;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Framebuffers\RingFramebuffer;
use Surface\Contracts\Framebuffers\ScanDirection;
use Surface\Contracts\Framebuffers\Spans;

/**
 * Drives one golden fixture through any FramebufferDriver and asserts every
 * step. Both drivers must pass every fixture: the fixtures are the contract
 * between them.
 *
 * Fixture: { name, kind, format, width, height, page_rows?, frames?, steps: [ { ops: [...], expect: {...} } ] }
 *
 * ops:    ["set",x,y,v] ["segment",x,y,w,h,v] ["fill",v] ["clear"] ["pixels",[[x,y,v],...]]
 *         ["region_set",[[x,y],...],v] ["rgba8",hex] ["epoch"] ["page",n]
 *         ["present"] ["hold"] ["release"] ["repair"] ["spans",[[y,x,length,coverage],...],0xRRGGBBAA]
 * expect: bytes_hex, get:[[x,y,v],...], region:[[x,y,w,h],hex], rgba8_hex, damage:[[x,y,w,h],...],
 *         pages:n, page:n, page_region:[n,[x,y,w,h]], pages_touching:[[x,y,w,h],[n,...]],
 *         granularity:[uw,uh], flush:[{format,bytes_hex},...], preserves:bool, pointer_zero:true,
 *         paper:v, channel:[[ink,hex],...],
 *         serial:n, ready:bool, age:n, frame:[[age,hex|null],...], ring_damage:[[since|null,[[x,y,w,h],...]],...]
 */
final class FixtureRunner
{
    public const string DIR = __DIR__.'/../../Framebuffers/fixtures';

    /** @return array<string, array{string}> A Pest dataset: fixture basename => [path]. */
    public static function files(): array
    {
        $files = glob(self::DIR.'/*.json') ?: [];
        sort($files);

        return array_combine(array_map(basename(...), $files), array_map(fn (string $file): array => [$file], $files));
    }

    public static function spec(array $f): FormatSpec
    {
        $palette = null;
        if (! empty($f['palette'])) {
            $palette = new ChannelPalette(...array_map(
                fn (array $c): ChannelSpec => new ChannelSpec($c['color'], $c['inverted'] ?? false, $c['code'] ?? null),
                $f['palette'],
            ));
        }

        return new FormatSpec(
            PixelFormat::from($f['pixel_format']),
            BitDepth::from($f['bit_depth']),
            ($f['scan'] ?? 'top_to_bottom') === 'top_to_bottom' ? ScanDirection::TOP_TO_BOTTOM : ScanDirection::BOTTOM_TO_TOP,
            isset($f['bit_order']) ? ($f['bit_order'] === 'msb_first' ? BitOrder::MSB_FIRST : BitOrder::LSB_FIRST) : null,
            isset($f['endianness']) ? ($f['endianness'] === 'lsb' ? Endianness::LSB : Endianness::MSB) : null,
            isset($f['page_axis']) ? ($f['page_axis'] === 'horizontal' ? PageAxis::HORIZONTAL : PageAxis::VERTICAL) : null,
            $palette,
            isset($f['channel_order']) ? constant(ChannelOrder::class.'::'.strtoupper($f['channel_order'])) : null,
        );
    }

    public static function run(FramebufferDriver $driver, string $file): void
    {
        $fx = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $spec = self::spec($fx['format']);
        $w = $fx['width'];
        $h = $fx['height'];
        $buffer = match ($fx['kind']) {
            'full' => $driver->full($spec, $w, $h),
            'dirty' => $driver->dirty($spec, $w, $h),
            'epaper' => $driver->epaper($spec, $w, $h),
            'paged' => $driver->paged($spec, $w, $h, $fx['page_rows']),
            'ring' => $driver->ring($spec, $w, $h, $fx['frames']),
        };

        $held = [];
        foreach ($fx['steps'] as $i => $step) {
            foreach ($step['ops'] ?? [] as $op) {
                self::apply($buffer, $op, $held);
            }
            self::check($driver, $buffer, $spec, $step['expect'] ?? [], "{$fx['name']} step {$i}");
        }
    }

    /** @param list<Framebuffer> $held Frames a ring fixture is holding, oldest first. */
    private static function apply(Framebuffer $b, array $op, array &$held): void
    {
        match ($op[0]) {
            'set' => $b->setPixel($op[1], $op[2], $op[3]),
            'segment' => $b->setSegment($op[1], $op[2], $op[3], $op[4], $op[5]),
            'fill' => $b->fill($op[1]),
            'clear' => $b->clear(),
            'pixels' => $b->setPixels($op[1]),
            'region_set' => $b->setRegion($op[1], $op[2]),
            'rgba8' => $b->blitFrom(new Rgba8Source(hex2bin($op[1]), $b->viewportWidth(), $b->viewportHeight())),
            'spans' => $b->paintSpans(implode('', array_map(fn (array $span): string => Spans::pack(...$span), $op[1])), $op[2]),
            'epoch' => $b instanceof DamageTrackingFramebuffer ? $b->beginEpoch() : throw new LogicException('epoch on a buffer that tracks no damage'),
            'page' => $b instanceof PagedFramebuffer ? $b->setPage($op[1]) : throw new LogicException('page on a non-paged buffer'),
            'present' => self::ring($b)->present(),
            'repair' => self::ring($b)->repair(),
            'hold' => $held[] = self::ring($b)->hold(),
            'release' => self::ring($b)->release(array_shift($held) ?? throw new LogicException('release without a hold')),
        };
    }

    private static function ring(Framebuffer $b): RingFramebuffer
    {
        return $b instanceof RingFramebuffer ? $b : throw new LogicException('a ring op on a non-ring buffer');
    }

    /** @return list<array{int, int, int, int}> */
    private static function rects(array $regions): array
    {
        return array_map(fn (Region $r): array => [$r->x, $r->y, $r->width, $r->height], $regions);
    }

    private static function check(FramebufferDriver $driver, Framebuffer $b, FormatSpec $spec, array $e, string $at): void
    {
        if (isset($e['bytes_hex'])) {
            expect(bin2hex($b->flush($spec)))->toBe($e['bytes_hex'], "{$at}: bytes");
            expect($b->flush($spec, true))->toBe(array_values(unpack('C*', hex2bin($e['bytes_hex']))), "{$at}: bytes as array");
        }
        foreach ($e['get'] ?? [] as [$x, $y, $v]) {
            expect($b->getPixel($x, $y))->toBe($v, "{$at}: get({$x},{$y})");
        }
        if (isset($e['region'])) {
            [[$x, $y, $rw, $rh], $hex] = $e['region'];
            expect(bin2hex($b->flushRegion(new Region($x, $y, $rw, $rh), $spec)))->toBe($hex, "{$at}: region");
        }
        if (isset($e['rgba8_hex'])) {
            expect(bin2hex($b->toRgba8()))->toBe($e['rgba8_hex'], "{$at}: rgba8");
        }
        if (isset($e['damage'])) {
            expect($b)->toBeInstanceOf(DamageTrackingFramebuffer::class);
            expect(self::rects($b->damage()))->toBe($e['damage'], "{$at}: damage");
        }
        if (isset($e['pages'])) {
            expect($b->pages())->toBe($e['pages'], "{$at}: pages");
        }
        if (isset($e['page'])) {
            expect($b->page())->toBe($e['page'], "{$at}: page");
        }
        if (isset($e['page_region'])) {
            [$n, $rect] = $e['page_region'];
            expect(self::rects([$b->pageRegion($n)]))->toBe([$rect], "{$at}: page_region");
        }
        if (isset($e['pages_touching'])) {
            [[$x, $y, $rw, $rh], $pages] = $e['pages_touching'];
            expect($b->pagesTouching(new Region($x, $y, $rw, $rh)))->toBe($pages, "{$at}: pages_touching");
        }
        if (isset($e['granularity'])) {
            $g = $b->damageGranularity();
            expect([$g->unit_width, $g->unit_height])->toBe($e['granularity'], "{$at}: granularity");
        }
        foreach ($e['flush'] ?? [] as $f) {
            expect(bin2hex($b->flush(self::spec($f['format']))))->toBe($f['bytes_hex'], "{$at}: flush to ".json_encode($f['format']));
        }
        if (isset($e['preserves'])) {
            expect($b->preservesContentsOnPresent())->toBe($e['preserves'], "{$at}: preserves");
        }
        if (isset($e['pointer_zero'])) {
            // native keeps its bytes in PHP (pointer 0); extended answers a real address
            expect($b->pointer() === 0)->toBe($driver->driver() === 'native', "{$at}: pointer");
        }
        if (isset($e['paper'])) {
            expect($b)->toBeInstanceOf(ePaperFramebuffer::class);
            expect($b->paper())->toBe($e['paper'], "{$at}: paper");
        }
        foreach ($e['channel'] ?? [] as [$ink, $hex]) {
            expect(bin2hex($b->channelDump(EInkColor::from($ink))))->toBe($hex, "{$at}: channel {$ink}");
        }
        if (isset($e['serial'])) {
            expect(self::ring($b)->serial())->toBe($e['serial'], "{$at}: serial");
        }
        if (isset($e['ready'])) {
            expect(self::ring($b)->ready())->toBe($e['ready'], "{$at}: ready");
        }
        if (isset($e['age'])) {
            expect(self::ring($b)->age())->toBe($e['age'], "{$at}: age");
        }
        foreach ($e['frame'] ?? [] as [$age, $hex]) {
            $frame = self::ring($b)->frame($age);
            expect(is_null($frame) ? null : bin2hex($frame->flush($spec)))->toBe($hex, "{$at}: frame {$age}");
        }
        foreach ($e['ring_damage'] ?? [] as [$since, $rects]) {
            expect(self::rects(self::ring($b)->damage($since)))->toBe($rects, "{$at}: damage since ".json_encode($since));
        }
    }
}
