<?php
class EzDrawerLayer implements EzDataObject
{
    /**
     * @var IPainter
     */
    public $resource;

    /**
     * @var string
     */
    public $alias;

    /**
     * @var int
     */
    public $index;

    public $startX;

    public $startY;
}
