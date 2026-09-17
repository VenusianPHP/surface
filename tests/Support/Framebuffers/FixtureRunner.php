<?php

namespace Venusian\Surface\Tests\Support\Framebuffers;

use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\BitOrder;
use Surface\Contracts\Framebuffers\ChannelPalette;
use Surface\Contracts\Framebuffers\ChannelSpec;
use Surface\Contracts\Framebuffers\DamageTrackingFramebuffer;
use Surface\Contracts\Framebuffers\Endianness;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\MultiFrameFramebuffer;
use Surface\Contracts\Framebuffers\PagedFramebuffer;
use Surface\Contracts\Framebuffers\PageAxis;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Framebuffers\ScanDirection;

/**
 * Drives one golden fixture through any FramebufferDriver and asserts every
 * step. Fixture schema: { name, kind, format, width, height, page_rows?, frames?,
 * steps: [ { ops: [...], expect: {...} } ] }.
 *
 * ops:  ["set",x,y,v] ["segment",x,y,w,h,v] ["fill",v] ["clear"] ["pixels",[[x,y,v],...]]
 *       ["region_set",[[x,y],...],v] ["rgba8",hex] ["epoch"] ["page",n] ["present"]
 * expect: bytes_hex, get:[[x,y,v],...], region:[[x,y,w,h],hex], rgba8_hex, damage:[[x,y,w,h],...],
 *         pages:n, page:n, page_region:[n,[x,y,w,h]], granularity:[uw,uh], flush:[{format,bytes_hex},...],
 *         preserves:bool, pointer_zero:true (php answers 0, native answers an address)
 */
final class FixtureRunner
{
    public const DIR = __DIR__.'/../../Framebuffers/fixtures';

    /** @return list<string> */
    public static function files(): array
    {
        $files = glob(self::DIR.'/*.json');
        sort($files);

        return $files;
    }

    public static function spec(array $f): FormatSpec
    {
        $palette = null;
        if (! empty($f['palette'])) {
            $palette = new ChannelPalette(...array_map(
                fn (array $c) => new ChannelSpec($c['color'], $c['inverted'] ?? false, $c['code'] ?? null),
                $f['palette'],
            ));
        }

        return new FormatSpec(
            PixelFormat::from($f['pixel_format']),
            BitDepth::from($f['bit_depth']),
            ScanDirection::from(match ($f['scan'] ?? 'top_to_bottom') { 'top_to_bottom' => 0, 'bottom_to_top' => 1 }),
            isset($f['bit_order']) ? BitOrder::from($f['bit_order'] === 'msb_first' ? 0 : 1) : null,
            isset($f['endianness']) ? Endianness::from($f['endianness'] === 'lsb' ? 0 : 1) : null,
            isset($f['page_axis']) ? PageAxis::from($f['page_axis'] === 'horizontal' ? 0 : 1) : null,
            $palette,
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

        foreach ($fx['steps'] as $i => $step) {
            foreach ($step['ops'] ?? [] as $op) {
                self::apply($buffer, $op);
            }
            self::check($driver, $buffer, $spec, $step['expect'] ?? [], "{$fx['name']} step {$i}");
        }
    }

    private static function apply(Framebuffer $b, array $op): void
    {
        match ($op[0]) {
            'set' => $b->setPixel($op[1], $op[2], $op[3]),
            'segment' => $b->setSegment($op[1], $op[2], $op[3], $op[4], $op[5]),
            'fill' => $b->fill($op[1]),
            'clear' => $b->clear(),
            'pixels' => $b->setPixels($op[1]),
            'region_set' => $b->setRegion($op[1], $op[2]),
            'rgba8' => $b->blitFrom(self::rgba8Source($b, hex2bin($op[1]))),
            'epoch' => $b instanceof DamageTrackingFramebuffer ? $b->beginEpoch() : throw new \LogicException('epoch on a non-dirty buffer'),
            'page' => $b instanceof PagedFramebuffer ? $b->setPage($op[1]) : throw new \LogicException('page on a non-paged buffer'),
            'present' => $b instanceof MultiFrameFramebuffer ? $b->present() : throw new \LogicException('present on a non-ring buffer'),
        };
    }

    /** The rgba8 op loads pixels through the mapper: a B32 full buffer holding the bytes, blitted in. */
    private static function rgba8Source(Framebuffer $target, string $rgba8): Framebuffer
    {
        return new Rgba8Source($rgba8, $target->viewportWidth(), $target->viewportHeight());
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
            expect($b instanceof DamageTrackingFramebuffer)->toBeTrue();
            expect(array_map(fn (Region $r) => [$r->x, $r->y, $r->width, $r->height], $b->damage()))->toBe($e['damage'], "{$at}: damage");
        }
        if (isset($e['pages'])) {
            expect($b->pages())->toBe($e['pages'], "{$at}: pages");
        }
        if (isset($e['page'])) {
            expect($b->page())->toBe($e['page'], "{$at}: page");
        }
        if (isset($e['page_region'])) {
            [$n, [$x, $y, $rw, $rh]] = $e['page_region'];
            expect($b->pageRegion($n))->toEqual(new Region($x, $y, $rw, $rh), "{$at}: page_region");
        }
        if (isset($e['granularity'])) {
            $g = $b->damageGranularity();
            expect([$g->unit_width, $g->unit_height])->toBe($e['granularity'], "{$at}: granularity");
        }
        foreach ($e['flush'] ?? [] as $f) {
            expect(bin2hex($b->flush(self::spec($f['format']))))->toBe($f['bytes_hex'], "{$at}: flush to {$f['format']['pixel_format']}");
        }
        if (isset($e['preserves'])) {
            expect($b->preservesContentsOnPresent())->toBe($e['preserves'], "{$at}: preserves");
        }
        if (isset($e['pointer_zero'])) {
            // php owns its bytes (pointer 0); native answers a real address
            expect($b->pointer() === 0)->toBe($driver->driver() === 'php', "{$at}: pointer");
        }
    }
}
