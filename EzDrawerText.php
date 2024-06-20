<?php
class EzDrawerText implements EzDataObject
{
    public $text;
    public $startX = 0;
    public $startY = 0;
    public $fontSize = 12;
    public $fontFilePath = '';

    /**
     * @var array{int, int, int}
     */
    public $rgb = [0,0,0];

    private $trueTypeBox = null;
    private $textWidth = 0;
    private $textHeight = 0;
    private $canvasWidth = 0;
    private $canvasHeight = 0;

    /**
     * @var array{int, int, int, float}
     */
    public $backgroundRgba = null;

    public function __construct($canvasWidth, $canvasHeight) {
        $this->canvasWidth = $canvasWidth;
        $this->canvasHeight = $canvasHeight;
        $this->trueTypeBox = imagettfbbox($this->fontSize, 0, $this->fontFilePath, $this->text);   //得到字符串虚拟方框四个点的坐标
        $this->textWidth = $this->trueTypeBox[2] - $this->trueTypeBox[0];
        $this->textHeight = $this->trueTypeBox[3] - $this->trueTypeBox[5];
        $this->startY += $this->textHeight;

        if (empty($fontFilePath)) {
            if (Application::isLinux()) {
                $this->fontFilePath = "";
            } else if (Application::isMac()) {
                $this->fontFilePath = "/Library/Fonts/Arial Unicode.ttf";
            } else {
                $this->fontFilePath = "C:\Windows\Fonts\simsun.ttc";
            }
        }
    }

    public function setBackgroundColor(int $r, int $g, int $b, float $a) {
        $this->backgroundRgba = func_get_args();
    }

    public function alignCenter() {
        $this->startX = ($this->canvasWidth-$this->textWidth)/2;
    }

    public function alignLeft() {
        $this->startX = 0;
    }

    public function alignRight() {
        $this->startX = $this->canvasWidth - $this->textWidth;
    }

    public function valignCenter() {
        $this->startY = ($this->canvasHeight-$this->textHeight)/2;
    }

    public function valignTop() {
        $this->startY += $this->textHeight;
    }

    public function valignBottom() {
        $this->startY = $this->canvasHeight - $this->textHeight;
    }

    public function getTextWidth():int {
        return $this->textWidth;
    }

    public function getTextHeight():int {
        return $this->textHeight;
    }
}
