<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Style;

/** Border collection: top/bottom/left/right/diagonal plus allBorders. */
class Borders
{
    public const DIAGONAL_NONE = 0;
    public const DIAGONAL_UP = 1;
    public const DIAGONAL_DOWN = 2;
    public const DIAGONAL_BOTH = 3;

    private array $sides = [];

    private int $diagonalDirection = self::DIAGONAL_NONE;

    public function __construct(private Style $style)
    {
    }

    public function getDiagonal(): Border
    {
        return $this->side('diagonal');
    }

    public function setDiagonalDirection(int $direction): static
    {
        $this->diagonalDirection = $direction;
        $this->style->mergeComponent('borders', ['diagonalDirection' => $direction]);

        return $this;
    }

    public function getDiagonalDirection(): int
    {
        return $this->diagonalDirection;
    }

    public function getTop(): Border
    {
        return $this->side('top');
    }

    public function getBottom(): Border
    {
        return $this->side('bottom');
    }

    public function getLeft(): Border
    {
        return $this->side('left');
    }

    public function getRight(): Border
    {
        return $this->side('right');
    }

    public function getAllBorders(): Border
    {
        return $this->side('allBorders');
    }

    public function getOutline(): Border
    {
        return $this->side('outline');
    }

    private function side(string $position): Border
    {
        return $this->sides[$position] ??= new Border($this->style, $position);
    }
}
