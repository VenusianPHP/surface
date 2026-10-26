<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\PrimitiveFactory;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKPrimitive as Child;
use Surface\Contracts\Windows\Primitives\TKTabView as PrimitiveContract;
use Surface\Contracts\Windows\WindowException;

/**
 * Pages are columns added through addPage(); no other child is admitted. Removing a page's column
 * removes its tab. Selection lives in the native, so selected() follows the user's switches.
 */
abstract class TKTabView extends TKPrimitiveGroup implements PrimitiveContract
{
    /** @var array<string, string> page name => title */
    protected array $titles = [];

    private ?string $adding = null;

    private ?int $adding_at = null;

    public function addPage(string $name, string $title, ?int $index = null): TKColumn
    {
        $this->live();
        $count = count($this->children);
        if (! is_null($index) && ($index < 0 || $index > $count)) {
            throw new WindowException("Tab index {$index} is out of range (0..{$count}).");
        }
        $this->adding = $title;
        $this->adding_at = $index === $count ? null : $index;
        try {
            $page = $this->create($name, true, fn (PrimitiveFactory $factory) => $factory->mintColumn($this, $name, 0, 0));
        } finally {
            $this->adding = null;
            $this->adding_at = null;
        }
        if (! is_null($index) && $index < $count) {
            // The page went in at the end of the children: move it to its tab's place, so pages() follows the tabs.
            $children = $this->children;
            unset($children[$name]);
            $this->children = array_merge(array_slice($children, 0, $index, true), [$name => $page], array_slice($children, $index, null, true));
        }

        return $page;
    }

    public function pages(): array
    {
        return array_keys($this->children);
    }

    public function setTitle(string $page, string $title): static
    {
        $this->live();
        $column = $this->page($page);
        $this->titles[$page] = $title;
        $this->applyTitle($column, $title);

        return $this;
    }

    public function title(string $page): string
    {
        $this->page($page);

        return $this->titles[$page];
    }

    public function selected(): ?string
    {
        $this->live();

        return $this->children === [] ? null : $this->nativeSelected();
    }

    public function select(string $page): static
    {
        $this->live();
        $this->applySelect($this->page($page));

        return $this;
    }

    public function forgetChild(Child $child): void
    {
        parent::forgetChild($child);
        unset($this->titles[$child->name()]);
    }

    protected function admitChild(string $name, bool $group, Placement $placement): void
    {
        if (is_null($this->adding)) {
            throw new WindowException("Tab view '{$this->path()}' takes pages through addPage().");
        }
    }

    protected function insertNative(Child $child): void
    {
        /** @var TKColumn $child */
        $this->titles[$child->name()] = $this->adding;
        $this->insertPage($child, $this->adding, $this->adding_at);
    }

    protected function defaultPlacement(): Placement
    {
        return Placement::next();
    }

    /**
     * @param string $page
     * @return TKColumn
     * @throws WindowException When there is no such page.
     */
    protected function page(string $page): TKColumn
    {
        $column = $this->children[$page] ?? null;
        if (! $column instanceof TKColumn) {
            throw new WindowException("Tab view '{$this->path()}' has no page '{$page}'.");
        }

        return $column;
    }

    /**
     * @param TKColumn $page
     * @param string $title
     * @return void
     */
    /**
     * Put the page's tab before the tab at $index; null appends.
     *
     * @param TKColumn $page
     * @param string $title
     * @param int|null $index
     * @return void
     */
    abstract protected function insertPage(TKColumn $page, string $title, ?int $index): void;

    /**
     * @param TKColumn $page
     * @param string $title
     * @return void
     */
    abstract protected function applyTitle(TKColumn $page, string $title): void;

    /**
     * @param TKColumn $page
     * @return void
     */
    abstract protected function applySelect(TKColumn $page): void;

    /**
     * @return string|null the selected page's name
     */
    abstract protected function nativeSelected(): ?string;
}
