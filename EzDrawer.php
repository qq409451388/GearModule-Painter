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
    private $layers;

    /**
     * @description  {@link IMAGETYPE_JPEG,IMAGETYPE_GIF,IMAGETYPE_PNG}
     * @var array<int>
     */
    private $allowsImageTypes;

    private $hasExtruding = false;

    private $isRoot = true;

    /*** cache ***/
    private $cacheSourceImageWidth;
    private $cacheSourceImageHeight;

    public function __construct() {
        $this->layers = [];
        $this->allowsImageTypes = [
            IMAGETYPE_JPEG,
            IMAGETYPE_GIF,
            IMAGETYPE_PNG,
        ];
    }

    public static function createEmptyCanvas($w = 200, $h = 200) {
        $d = new EzDrawer();
        $d->workResource = imagecreatetruecolor($w, $h);
        DBC::assertNotEquals(false, $d->workResource, "[EzDrawer] Cannot create image.");
        return $d;
    }

    public static function createFromImage($file) {
        list($w, $h) = getimagesize($file);
        $d = self::createEmptyCanvas($w, $h);
        $d->createLayerFromImage($file);
        DBC::assertNotEquals(false, $d->workResource, "[EzDrawer] Cannot create image.");
        return $d;
    }

    /**
     * @param             $filePath
     * @param int         $index 图层位置，值越小，位置越低，0为最低
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
        $drawer = new self();
        $drawer->isRoot = false;
        $drawer->workResource = imagecreatefromstring(file_get_contents($filePath));
        $resource = $drawer;
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

        DBC::assertTrue(!$this->isRoot || $this->hasExtruding, "[EzDrawer] Cannot scale image. must not root or be extruding first!");
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
                $this->allowsImageTypes = array_intersect($layer->resource->getAllowImageTypes(), $this->allowsImageTypes);
                imagecopyresampled($this->workResource, $resource,
                    $layer->startX, $layer->startY, 0, 0,
                    $layer->resource->getImageWidthPixel(), $layer->resource->getImageHeightPixel(),
                    $layer->resource->getImageWidthPixel(), $layer->resource->getImageHeightPixel());
            }
        } else {
            $this->hasExtruding = true;
            return $this->workResource;
        }
    }

    public function output($outputFilePath, $fileType = IMAGETYPE_JPEG):void {
        $this->extruding();
        DBC::assertTrue(in_array($fileType, $this->allowsImageTypes, true), "[EzDrawer] Unsupport fileType, Cannot output image type.", 0, GearIllegalArgumentException::class);
        $outputFilePath = $this->renameWithNewExtForOutput($outputFilePath, $fileType);
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
        Logger::info("[EzDrawer] Output file [$outputFilePath] was created.");
    }

    private function renameWithNewExtForOutput($fileName, $targetFileType) {
        $pathInfo = pathinfo($fileName);
        $extension = $pathInfo['extension'];
        switch ($targetFileType) {
            case IMAGETYPE_GIF:
                return EzStringUtils::replaceOnce(".".$extension, ".gif", $fileName);
            case IMAGETYPE_PNG:
                return EzStringUtils::replaceOnce(".".$extension, ".png", $fileName);
            case IMAGETYPE_JPEG:
            default:
                return EzStringUtils::replaceOnce(".".$extension, ".jpg", $fileName);
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

    public function getAllowImageTypes():array {
        return $this->allowsImageTypes;
    }

    public function crop(int $newWidth, int $newHeight, int $startX, int $startY, int $cornerRadius = 0): void {
        DBC::assertTrue(!$this->isRoot || $this->hasExtruding, "[EzDrawer] Cannot crop image. must not root or be extruding first!");
        $croppedImage = imagecreatetruecolor($newWidth, $newHeight);

        // 将源图像的指定区域复制到裁剪后的图像中
        imagecopyresampled(
            $croppedImage,         // 目标图像
            $this->workResource,   // 源图像
            0, 0,                  // 目标图像上的 x 和 y 坐标
            $startX, $startY,      // 源图像上的 x 和 y 坐标
            $newWidth, $newHeight, // 目标图像的宽度和高度
            $newWidth, $newHeight  // 源图像的宽度和高度
        );
        $this->clearCacheImageWidthHeightData();
        $this->workResource = $croppedImage;

        // 如果有圆角半径，则绘制圆角矩形
        if ($cornerRadius > 0) {
            $cornerImage = imagecreatetruecolor($newWidth, $newHeight);
            // 设置透明背景
            $transparent = imagecolorallocatealpha($cornerImage, 0, 0, 0, 127);
            imagefill($cornerImage, 0, 0, $transparent);
            imagesavealpha($cornerImage, true);
            $this->imagefilledroundedrect($cornerImage, $croppedImage, $newWidth, $newHeight, $cornerRadius);
            $this->clearCacheImageWidthHeightData();
            $this->workResource = $cornerImage;
        }
    }

    private function imagefilledroundedrect($img, $src_img, $w, $h, $radius) {
        $r = $radius;
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $rgbColor = imagecolorat($src_img, $x, $y);
                //在四角的范围内选择画
                if (($x >= $radius && $x <= ($w - $radius)) || ($y >= $radius && $y <= ($h - $radius))) {
                    //不在四角的范围内,直接画
                    imagesetpixel($img, $x, $y, $rgbColor);
                }
                //上左
                $y_x = $r;      //圆心X坐标
                $y_y = $r;      //圆心Y坐标
                if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                    imagesetpixel($img, $x, $y, $rgbColor);
                }
                //上右
                $y_x = $w - $r; //圆心X坐标
                $y_y = $r;      //圆心Y坐标
                if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                    imagesetpixel($img, $x, $y, $rgbColor);
                }
                //下左
                $y_x = $r;      //圆心X坐标
                $y_y = $h - $r; //圆心Y坐标
                if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                    imagesetpixel($img, $x, $y, $rgbColor);
                }
                //下右
                $y_x = $w - $r; //圆心X坐标
                $y_y = $h - $r; //圆心Y坐标
                if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                    imagesetpixel($img, $x, $y, $rgbColor);
                }
            }
        }
    }

    public function opacity(float $factor): void {
        DBC::assertInRange("[0, 1]", $factor, "[EzDrawer] Please Input a valid opacity factor in range [0, 1], Cannot opacize the image.",
            0, GearIllegalArgumentException::class);
        if ($this->isRoot) {
            foreach ($this->layers as $layer) {
                $layer->resource->opacity($factor);
            }
            return;
        }
        $this->allowsImageTypes = [IMAGETYPE_PNG];
        $width = $this->getImageWidthPixel();
        $height = $this->getImageHeightPixel();
        $newImage = imagecreatetruecolor($width, $height);

        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
        imagefill($newImage, 0, 0, $transparent);
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $color = imagecolorat($this->workResource, $x, $y);
                $alpha = ($color >> 24) & 0x7F; // 获取Alpha值
                $alpha = round(127 - (127 - $alpha) * $factor); // 调整Alpha值
                $newColor = imagecolorallocatealpha($newImage, ($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF, $alpha);
                imagesetpixel($newImage, $x, $y, $newColor);
            }
        }
        $this->workResource = $newImage;
    }

    private function clearCacheImageWidthHeightData() {
        $this->cacheSourceImageWidth = $this->cacheSourceImageHeight = null;
    }

    public function addText(EzDrawerText $text): void {
        DBC::assertTrue(is_file($text->fontFilePath),
            "[EzDrawer] Please Input a valid font file path.", 0, GearIllegalArgumentException::class);
        $color = imagecolorallocate($this->workResource, $text->rgb[0], $text->rgb[1], $text->rgb[2]);

        if (!is_null($text->backgroundRgba)) {
            $dst = imagecreatetruecolor($text->getTextWidth(),$text->getTextHeight());
            // todo
            /*imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, $text->backgroundRgba[0], $text->backgroundRgba[1], $text->backgroundRgba[2], 127);
            imagefill($dst, 0, 0, $transparent);
            imagettftext($dst,$size,0,$x,$y,$black, $file, $content);*/

        } else {
            imagettftext($this->workResource ,$text->fontSize,0, $text->startX, $text->startY , $color, $text->fontFilePath, $text->text);
        }
    }

    public function addTextLite(string $text, int $fontSize, int $startX = 0, int $startY = 0, array $rgb = [0,0,0], string $fontFilePath = ''): void {
        if (empty($fontFilePath)) {
            if (Application::isLinux()) {
                $fontFilePath = "";
            } else if (Application::isMac()) {
                $fontFilePath = "/System/Library/Fonts/Helvetica.ttc";
                $fontFilePath = "/Library/Fonts/Arial Unicode.ttf";
            } else {
                $fontFilePath = "C:\Windows\Fonts\simsun.ttc";
            }
        }
        DBC::assertTrue(is_file($fontFilePath), "[EzDrawer] Please Input a valid font file path.", 0, GearIllegalArgumentException::class);
        $color = imagecolorallocate($this->workResource, $rgb[0], $rgb[1], $rgb[2]);
        imagettftext($this->workResource ,$fontSize,0, $startX, $startY , $color, $fontFilePath, $text);
    }

    private function colorDistance($c1, $c2) {
        return sqrt(
            pow(($c1[0] - $c2[0]), 2) +
            pow(($c1[1] - $c2[1]), 2) +
            pow(($c1[2] - $c2[2]), 2)
        );
    }

    private function setTransparency() {
        $transparentColor = imagecolorallocatealpha($this->workResource, 0,0,0, 127);
        imagecolortransparent($this->workResource, $transparentColor);

        imagefill($this->workResource, 0, 0, $transparentColor);
        imagesavealpha($this->workResource, true);
    }

    private function regionGrow($x, $y, $targetColor, $threshold, &$visited) {
        $width = $this->getImageWidthPixel();
        $height = $this->getImageHeightPixel();
        $queue = [[$x, $y]];
        //$targetColor = $this->straw($x, $y);

        while (count($queue) > 0) {
            list($cx, $cy) = array_shift($queue);

            if ($cx < 0 || $cy < 0 || $cx >= $width || $cy >= $height || $visited[$cy][$cx]) {
                continue;
            }

            $currentColor = $this->straw($cx, $cy);
            $colorDistance = $this->colorDistance($currentColor, $targetColor);
            if ($colorDistance > $threshold) {
                continue;
            }

            $visited[$cy][$cx] = true;

            // Mark as transparent if outside the region
            //$colorToSet = ($threshold == 0 || $this->colorDistance($currentColor, $targetColor) <= $threshold) ? $targetColor : $currentColor;
            //imagesetpixel($this->workResource, $cx, $cy, imagecolorallocate($this->workResource, $colorToSet[0], $colorToSet[1], $colorToSet[2]));
            array_push($queue, [$cx + 1, $cy], [$cx - 1, $cy], [$cx, $cy + 1], [$cx, $cy - 1]);
        }
    }

    public function straw(int $x, int $y, $mix = 1) {
        if (empty($this->layers)) {
            return false;
        }
        foreach ($this->layers as $layer) {
            if ($this->isOnThisLayer($x, $y, $layer)) {
                $index = imagecolorat($this->workResource, $x, $y);
                $colors = imagecolorsforindex($this->workResource, $index);
                return [$colors['red'], $colors['green'], $colors['blue']];
            }
        }
        return false;
    }

    public function select(int $x, int $y, $mix = 1):EzDrawerSelector {
        $rgb = $this->straw($x, $y, $mix);
        return $this->selectFromColor($rgb);
    }

    public function selectFromColor(array $rgb = [], $threshold = 0):EzDrawerSelector {
        $width = $this->getImageWidthPixel();
        $height = $this->getImageHeightPixel();
        $visited = array_fill(0, $height, array_fill(0, $width, false));

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (!$visited[$y][$x]) {
                    $this->regionGrow($x, $y, $rgb, $threshold, $visited);
                }
            }
        }

        $selector = new EzDrawerSelector();
        $positions = [];
        foreach ($visited as $y => $xList) {
            foreach ($xList as $x => $bool) {
                if ($bool) {
                    $positions[] = [$x, $y];
                }
            }
        }
        $selector->selectedPositions = $positions;
        return $selector;
    }
}
