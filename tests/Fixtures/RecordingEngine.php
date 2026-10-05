<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Drawing\RenderingEngine;
use Surface\Framebuffers\Native\NativeFullFramebuffer;

/** A rendering engine that draws nothing: it keeps what it was handed to execute, so tests can read the recorded commands. */
final class RecordingEngine extends RenderingEngine
{
    /** @var list<list<array<int, mixed>>> One command list per execution. */
    public array $executed = [];

    private Framebuffer $framebuffer;

    /**
     * @param  int|Framebuffer  $framebuffer  A surface size, or the framebuffer the engine draws over.
     */
    public function __construct(int|Framebuffer $framebuffer = 100, int $height = 80)
    {
        $this->framebuffer = $framebuffer instanceof Framebuffer
            ? $framebuffer
            : new NativeFullFramebuffer(FormatSpec::rgba8(), $framebuffer, $height);
    }

    public function name(): string
    {
        return 'recording';
    }

    public function framebuffer(): Framebuffer
    {
        return $this->framebuffer;
    }

    protected function execute(array $commands): void
    {
        $this->executed[] = $commands;
    }

    /** @return list<array<int, mixed>> The commands of one frame drawn by $draw. */
    public function commandsOf(callable $draw): array
    {
        $this->frame($draw);

        return $this->executed[array_key_last($this->executed)];
    }
}
