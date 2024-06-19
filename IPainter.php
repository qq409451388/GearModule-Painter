<?php
interface IPainter extends EzComponent
{
    /*** load image ***/
    public static function createEmptyCanvas($w = 200, $h = 200);
    public static function createFromImage($filePath);
    public function createLayerFromImage($filePath, int $index = -1, string $alias = null, int $startX = 0, int $startY = 0);
    public function createLayer(IPainter $painter, int $index = -1, string $alias = null, int $startX = 0, int $startY = 0);

    /*** get info from image ***/
    public function getImageWidthPixel():int;
    public function getImageHeightPixel():int;

    /*** operate image ***/
    public function scale($newWidth, $newHeight):void;
    public function scaleWithFactor(float $factor):void;
    public function setBackgroundColorWhite();
    public function setBackgroundColorBlack();
    public function setBackgroundColor($red, $green, $blue);

    /*** output image ***/
    /**
     * @return resource|GdImage
     */
    public function extruding();
    public function output($outputFilePath, $fileType = IMAGETYPE_JPEG):void;
}
