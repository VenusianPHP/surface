<?php

namespace Surface\Contracts\Windows\Mail\View;

readonly class RowSelected extends PrimitiveEventOccurred implements PrimitiveMail
{
    /**
     * @param int|null $row null = selection cleared
     * @param array<string, string>|null $cells the selected row as column id => cell text
     */
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public readonly ?int $row,
        public readonly ?array $cells,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return "view.row-selected.{$this->window}.{$this->path}";
    }
}
