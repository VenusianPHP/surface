<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\ImageScaling;
use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKImage as PrimitiveContract;
use Surface\Contracts\Windows\ToolkitWindow;

abstract class TKImage extends TKPrimitive implements PrimitiveContract
{
    protected ImageScaling $scaling = ImageScaling::FIT;

    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        protected ?string $file,
    ) {
        parent::__construct($name, $window, $parent, $placement);
    }

    public function file(): ?string
    {
        return $this->file;
    }

    public function setFile(?string $file): static
    {
        $this->live();
        $this->file = $file;
        $this->applyFile($file);

        return $this;
    }

    public function setScaling(ImageScaling $scaling): static
    {
        $this->live();
        $this->scaling = $scaling;
        $this->applyScaling($scaling);

        return $this;
    }

    /**
     * @param string|null $file null clears the image
     * @return void
     */
    abstract protected function applyFile(?string $file): void;

    /**
     * @param ImageScaling $scaling
     * @return void
     */
    abstract protected function applyScaling(ImageScaling $scaling): void;
}
