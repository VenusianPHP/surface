<?php

namespace Surface\Fonts\Extended;

use FT_Face;
use FT_Library;
use Surface\Contracts\Fonts\FontException;

/**
 * Every open FreeType face, keyed by file, face index and pixel size (to 1/64 px), on one FT_Library.
 * FreeType and HarfBuzz handles are not freed when PHP drops them: close() releases the HarfBuzz fonts, then
 * the faces, then the library.
 */
class FaceCache
{
    /** How many faces were opened; read by tests. */
    public private(set) int $opened = 0;

    private ?FT_Library $library = null;

    /** @var array<string, Face> */
    private array $faces = [];

    public function face(FontFile $file, float $pixelSize): Face
    {
        $size = (int) round($pixelSize * 64);
        if ($size <= 0) {
            throw FontException::badSize($pixelSize);
        }
        $key = "{$file->path}#{$file->index}@{$size}";
        if (isset($this->faces[$key])) {
            return $this->faces[$key];
        }

        $face = null;
        if (FT_New_Face($this->library(), $file->path, $file->index, $face) !== 0 || ! $face instanceof FT_Face) {
            throw FontException::library("FreeType could not open {$file->path} (face {$file->index})");
        }
        // 26.6 points at 72 dpi are pixels, so a fractional pixel size survives.
        if (FT_Set_Char_Size($face, 0, $size, 72, 72) !== 0) {
            FT_Done_Face($face);

            throw FontException::library("FreeType could not size {$file->path} to {$pixelSize} px");
        }
        $this->opened++;

        return $this->faces[$key] = new Face($file, $size / 64, $face);
    }

    public function close(): void
    {
        foreach ($this->faces as $face) {
            $face->close();
        }
        foreach ($this->faces as $face) {
            FT_Done_Face($face->ftFace());
        }
        $this->faces = [];
        if (! is_null($this->library)) {
            FT_Done_FreeType($this->library);
            $this->library = null;
        }
    }

    private function library(): FT_Library
    {
        if (is_null($this->library)) {
            $library = null;
            if (FT_Init_FreeType($library) !== 0 || ! $library instanceof FT_Library) {
                throw FontException::library('FreeType could not start');
            }
            $this->library = $library;
        }

        return $this->library;
    }
}
