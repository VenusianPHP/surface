<?php

namespace Surface\Contracts\Framebuffers;

/**
 * A framebuffer whose pixels can be handed to another process or API as a
 * Linux dmabuf: one plane, the frame as it stands after the last present.
 *
 * Each present writes into an export the importer does not hold and holds it
 * for the importer: an importer that builds over the export calls
 * releaseDmabuf() with its fd once it is done with it (GTK: when the texture
 * is finalized). Until then the export is neither written again nor closed,
 * not even when the framebuffer is re-made or let go. With every export held,
 * a present copies nothing and answers false.
 */
interface DmabufExportable
{
    /**
     * The export the last present wrote, or null when the framebuffer is not
     * being exported (no DMABUF surface was adopted, or nothing presented yet).
     *
     * @return array{fd: int, width: int, height: int, stride: int, offset: int, fourcc: int, modifier: int}|null
     */
    public function dmabuf(): ?array;

    /** The importer is done with the export it was handed with $fd: it may be written again, or closed. */
    public function releaseDmabuf(int $fd): void;
}
