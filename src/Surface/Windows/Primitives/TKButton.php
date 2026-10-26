<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\ButtonRole;
use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKButton as PrimitiveContract;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\NutsAndBolts\Color;

/**
 * The driver posts ButtonClicked from the native click; nothing here posts.
 */
abstract class TKButton extends TKPrimitive implements PrimitiveContract
{
    use ShowsButtonImage;

    protected ?FontSpec $font = null;

    protected ?Color $text_color = null;

    protected ?ButtonRole $role = null;

    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        protected string $label,
    ) {
        parent::__construct($name, $window, $parent, $placement);
    }

    public function label(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->live();
        $this->label = $label;
        $this->applyLabel($label);

        return $this;
    }

    public function setFont(FontSpec $font): static
    {
        $this->live();
        $this->font = $font;
        $this->applyFont($font);

        return $this;
    }

    public function setTextColor(?Color $color): static
    {
        $this->live();
        $this->text_color = $color;
        $this->applyTextColor($color);

        return $this;
    }

    public function setRole(?ButtonRole $role): static
    {
        $this->live();
        if ($role === $this->role) {
            return $this;
        }
        if (! is_null($role)) {
            $previous = self::holder($this->window, $role);
            if (! is_null($previous)) {
                $previous->role = null;
                $previous->applyRole(null);
            }
        }
        $this->role = $role;
        $this->applyRole($role);

        return $this;
    }

    /** A removed button holds no role. */
    public function role(): ?ButtonRole
    {
        return $this->isRemoved() ? null : $this->role;
    }

    /**
     * The button of $window that holds $role, or null.
     *
     * @param ToolkitWindow $window
     * @param ButtonRole $role
     * @return TKButton|null
     */
    public static function holder(ToolkitWindow $window, ButtonRole $role): ?TKButton
    {
        foreach ($window->registry()->all() as $primitive) {
            if ($primitive instanceof self && $primitive->role === $role) {
                return $primitive;
            }
        }

        return null;
    }

    /**
     * Show the role: the default button's look, or none.
     *
     * @param ButtonRole|null $role
     * @return void
     */
    abstract protected function applyRole(?ButtonRole $role): void;

    /**
     * @param string $label
     * @return void
     */
    abstract protected function applyLabel(string $label): void;

    /**
     * @param FontSpec $font
     * @return void
     */
    abstract protected function applyFont(FontSpec $font): void;

    /**
     * @param Color|null $color null restores the toolkit's own
     * @return void
     */
    abstract protected function applyTextColor(?Color $color): void;
}
