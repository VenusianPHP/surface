<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Drawing\Executor;
use Surface\Contracts\Drawing\ExecutorCapabilities;
use Surface\Contracts\Drawing\TextureHandle;
use Surface\Contracts\Drawing\Topology;
use Surface\Contracts\Drawing\Transform;
use Surface\Contracts\NativeWindows\Views\Color;

/** An Executor that records every call and answers what a test tells it to. */
final class FakeExecutor implements Executor
{
    public ExecutorCapabilities $capabilities;

    public bool $begin_result = true;

    /** @var array{int, int} */
    public array $size = [0, 0];

    public bool $in_frame = false;

    public bool $released = false;

    public int $frames_begun = 0;

    public int $frames_ended = 0;

    /** @var list<string> */
    public array $calls = [];

    /** @var list<Color> */
    public array $clears = [];

    /** @var list<array<string, mixed>> */
    public array $draws = [];

    /** @var list<array{int, int, int, int}|null> null is unscissor */
    public array $scissors = [];

    /** @var array<int, array{string, int, int}> id => [rgba8, w, h] */
    public array $textures = [];

    /** @var list<int> */
    public array $released_textures = [];

    public string $pixels = '';

    public bool $throw_on_drawable = false;

    private int $next_texture = 1;

    /** When set, beginFrame() makes current first and endFrame() presents last — the GL contract. */
    public ?\Surface\Contracts\Drawing\GLSurface $gl = null;

    public function __construct(?ExecutorCapabilities $capabilities = null)
    {
        $this->capabilities = $capabilities ?? new ExecutorCapabilities(
            blending: true, depth: false, instancing: true, readback: true, max_texture_size: 4096,
        );
    }

    public function capabilities(): ExecutorCapabilities
    {
        return $this->capabilities;
    }

    public function resize(int $width, int $height): void
    {
        $this->calls[] = 'resize';
        $this->size = [$width, $height];
        if ($this->gl instanceof FakeGLSurface) {
            $this->gl->size = [$width, $height];
        }
    }

    public function drawableSize(): array
    {
        if ($this->throw_on_drawable) {
            throw new \RuntimeException('drawableSize unavailable');
        }

        return $this->size;
    }

    public function beginFrame(Color $clear): bool
    {
        $this->gl?->makeCurrent();
        $this->calls[] = 'beginFrame';
        if ($this->gl instanceof FakeGLSurface) {
            $this->gl->log[] = 'beginFrame';
        }
        if (! $this->begin_result) {
            return false;
        }
        $this->frames_begun++;
        $this->in_frame = true;
        $this->clears[] = $clear;

        return true;
    }

    public function viewport(int $x, int $y, int $width, int $height): void
    {
        $this->calls[] = 'viewport';
    }

    public function scissor(int $x, int $y, int $width, int $height): void
    {
        $this->calls[] = 'scissor';
        $this->scissors[] = [$x, $y, $width, $height];
    }

    public function unscissor(): void
    {
        $this->calls[] = 'unscissor';
        $this->scissors[] = null;
    }

    public function texture(string $rgba8, int $width, int $height): TextureHandle
    {
        $this->calls[] = 'texture';
        $id = $this->next_texture++;
        $this->textures[$id] = [$rgba8, $width, $height];

        return new TextureHandle($id, $width, $height);
    }

    public function releaseTexture(TextureHandle $texture): void
    {
        $this->calls[] = 'releaseTexture';
        $this->released_textures[] = $texture->id;
        unset($this->textures[$texture->id]);
    }

    public function draw(Topology $topology, string $vertices, int $vertex_count, Transform $transform, ?TextureHandle $texture = null, int $instances = 1): void
    {
        $this->calls[] = 'draw';
        $this->draws[] = [
            'topology' => $topology, 'vertices' => $vertices, 'count' => $vertex_count,
            'transform' => $transform, 'texture' => $texture, 'instances' => $instances,
            'indices' => null, 'index_count' => 0,
        ];
    }

    public function drawIndexed(Topology $topology, string $vertices, int $vertex_count, string $indices, int $index_count, Transform $transform, ?TextureHandle $texture = null, int $instances = 1): void
    {
        $this->calls[] = 'drawIndexed';
        $this->draws[] = [
            'topology' => $topology, 'vertices' => $vertices, 'count' => $vertex_count,
            'transform' => $transform, 'texture' => $texture, 'instances' => $instances,
            'indices' => $indices, 'index_count' => $index_count,
        ];
    }

    public function readPixels(): string
    {
        $this->calls[] = 'readPixels';
        if (! $this->in_frame) {
            throw DrawingException::outsideFrame('readPixels()');
        }

        return $this->capabilities->readback ? $this->pixels : '';
    }

    public function endFrame(): void
    {
        $this->calls[] = 'endFrame';
        if ($this->gl instanceof FakeGLSurface) {
            $this->gl->log[] = 'endFrame';
        }
        $this->in_frame = false;
        $this->frames_ended++;
        $this->gl?->present();
    }

    public function release(): void
    {
        $this->calls[] = 'release';
        $this->released = true;
    }

    /** @return list<list<float>> rows of 9 floats for one recorded draw */
    public function vertices(int $draw): array
    {
        $packed = $this->draws[$draw]['vertices'];
        $rows = [];
        for ($offset = 0; $offset + 36 <= strlen($packed); $offset += 36) {
            $rows[] = array_values(unpack('g9', substr($packed, $offset, 36)));
        }

        return $rows;
    }
}
