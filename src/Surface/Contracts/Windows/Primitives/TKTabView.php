<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Windows\WindowException;

/**
 * Pages, one column each, behind titled tabs. Removing a page's column removes its tab; the first page
 * added is selected.
 */
interface TKTabView extends TKPrimitive
{
    /**
     * A page before the one at $index; null appends. Posts no TabSelected. pages() follows the tabs.
     *
     * @param string $name
     * @param string $title
     * @param int|null $index
     * @return TKColumn the page's content
     * @throws WindowException When the index is outside 0..count.
     */
    public function addPage(string $name, string $title, ?int $index = null): TKColumn;

    /**
     * @return list<string> page names, in order
     */
    public function pages(): array;

    /**
     * @param string $page
     * @param string $title
     * @return $this
     */
    public function setTitle(string $page, string $title): static;

    /**
     * @param string $page
     * @return string
     */
    public function title(string $page): string;

    /**
     * @return string|null the selected page's name, null with no pages
     */
    public function selected(): ?string;

    /**
     * @param string $page
     * @return $this
     */
    public function select(string $page): static;
}
