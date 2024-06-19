<?php
class EzDrawer implements IPainter
{
    /**
     * @var GdImage|resource
     */
    private $workResource;

    /**
     * @var array<EzDrawerLayer>
     */
    private $layers = [];

    /*** cache ***/
    private $cacheSourceImageWidth;
    private $cacheSourceImageHeight;

    public static function createEmptyCanvas($w = 200, $h = 200) {
        $d = new EzDrawer();
        $d->workResource = imagecreatetruecolor($w, $h);
        DBC::assertNotEquals(false, $d->workResource, "[EzDrawer] Cannot create image.");
        return $d;
    }

    public static function createFromImage($file) {
        $d = new EzDrawer();
        //list($w, $h) = getimagesize($file);
        $d->workResource = imagecreatefromstring(file_get_contents($file));
        DBC::assertNotEquals(false, $d->workResource, "[EzDrawer] Cannot create image.");
        return $d;
    }

    /**
     * @param             $filePath
     * @param int         $index 图层位置，值越小，位置越大，0为最大
     * @param string|null $alias 别名
     * @param int         $startX 左上角X坐标
     * @param int         $startY 左上角Y坐标
     * @return void
     * @throws Exception
     */
    public function createLayerFromImage($filePath, int $index = -1, string $alias = null, int $startX = 0, int $startY = 0) {
        $layer = new EzDrawerLayer();
        if (-1 === $index) {
            $index = count($this->layers);
        }
        DBC::assertFalse(array_key_exists($index, $this->layers), "[EzDrawer] Duplicated index, Cannot create layer.");
        $layer->index = $index;
        if (empty($alias)) {
            $alias = md5($filePath);
        }
        $layer->alias = $alias;
        $resource = self::createFromImage($filePath);
        $layer->resource = $resource;
        $layer->startX = $startX;
        $layer->startY = $startY;
        $this->layers[$index] = $layer;
    }

    public function createLayer(IPainter $painter, int $index = -1, string $alias = null, int $startX = 0, int $startY = 0) {
        $layer = new EzDrawerLayer();
        if (-1 === $index) {
            $index = count($this->layers);
        }
        DBC::assertFalse(array_key_exists($index, $this->layers), "[EzDrawer] Duplicated index, Cannot create layer.");
        $layer->index = $index;
        if (empty($alias)) {
            $alias = "layer".$index;
        }
        $layer->alias = $alias;
        $layer->resource = $painter;
        $layer->startX = $startX;
        $layer->startY = $startY;
        $this->layers[$index] = $layer;
    }

    public function scale($w, $h):void {
        if ($w > $this->getImageWidthPixel()) {
            Logger::warn("[EzDrawer] The width pixed cant be greater than resource.");
        }

        if ($h > $this->getImageHeightPixel()) {
            Logger::warn("[EzDrawer] The height pixed cant be greater than resource.");
        }
        $newResource = imagecreatetruecolor($w, $h);
        imagecopyresampled($newResource, $this->workResource
            , 0, 0, 0, 0, $w, $h, $this->getImageWidthPixel(), $this->getImageHeightPixel());
        $this->workResource = $newResource;
    }

    public function scaleWithFactor(float $factor): void {
        DBC::assertNotEquals(0, $factor, "[EzDrawer] Cannot scale with 0 factor.");
        $sourceW = $this->getImageWidthPixel();
        $sourceH = $this->getImageHeightPixel();
        $w = (int)($sourceW*$factor);
        $h = (int)($sourceH*$factor);
        $this->scale($w, $h);
    }

    public function getImageWidthPixel():int {
        if (is_null($this->cacheSourceImageWidth)) {
            $this->cacheSourceImageWidth = imagesx($this->workResource);
        }
        return $this->cacheSourceImageWidth;
    }

    public function getImageHeightPixel():int {
        if (is_null($this->cacheSourceImageHeight)) {
            $this->cacheSourceImageHeight = imagesy($this->workResource);
        }
        return $this->cacheSourceImageHeight;
    }

    public function extruding() {
        if (is_array($this->layers) && !empty($this->layers)) {
            foreach ($this->layers as $layer) {
                $resource = $layer->resource->extruding();
                imagecopyresampled($this->workResource, $resource,
                    $layer->startX, $layer->startY, 0, 0,
                    $layer->resource->getImageWidthPixel(), $layer->resource->getImageHeightPixel(),
                    $layer->resource->getImageWidthPixel(), $layer->resource->getImageHeightPixel());
            }
        } else {
            return $this->workResource;
        }
    }

    public function output($outputFilePath, $fileType = IMAGETYPE_JPEG):void {
        $this->extruding();
        switch ($fileType) {
            case IMAGETYPE_GIF:
                imagegif($this->workResource, $outputFilePath);
                break;
            case IMAGETYPE_PNG:
                imagepng($this->workResource, $outputFilePath);
                break;
            case IMAGETYPE_JPEG:
            default:
                imagejpeg($this->workResource, $outputFilePath);
                break;
        }
    }

    public function setBackgroundColorWhite()
    {
        $this->setBackgroundColor(255, 255, 255);
    }

    public function setBackgroundColorBlack()
    {
        $this->setBackgroundColor(0, 0, 0);
    }

    public function setBackgroundColor($red, $green, $blue)
    {
        $color = imagecolorallocate($this->workResource, $red, $green, $blue);
        imagefilledrectangle($this->workResource, 0, 0, $this->getImageWidthPixel(), $this->getImageHeightPixel(),$color);
    }

    public function __destruct() {
        if (is_resource($this->workResource)) {
            imagedestroy($this->workResource);
        }
    }

}
