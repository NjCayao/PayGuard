<?php

namespace App\Services\Demo;

use RuntimeException;

class DemoReceiptImage
{
    public const WIDTH = 480;

    public const HEIGHT = 640;

    public function __construct()
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('The GD extension is required to generate demo receipts.');
        }
    }

    public function generate(
        string $amount,
        ?string $recipient,
        string $date,
        ?string $code,
        ?string $operation,
    ): string {
        $img = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        $white = imagecolorallocate($img, 255, 255, 255);
        $ink = imagecolorallocate($img, 30, 41, 59);
        $muted = imagecolorallocate($img, 100, 116, 139);
        $line = imagecolorallocate($img, 226, 232, 240);
        $bandBg = imagecolorallocate($img, 254, 243, 199);
        $bandInk = imagecolorallocate($img, 146, 64, 14);
        $footBg = imagecolorallocate($img, 248, 250, 252);

        imagefilledrectangle($img, 0, 0, self::WIDTH, self::HEIGHT, $white);
        imagefilledrectangle($img, 0, 0, self::WIDTH, 64, $bandBg);
        $this->center($img, 5, 'COMPROBANTE DE EJEMPLO', 24, $bandInk);

        $this->center($img, 4, 'Constancia de pago', 92, $muted);
        $this->center($img, 5, $amount, 122, $ink);
        imageline($img, 40, 168, self::WIDTH - 40, 168, $line);

        $this->row($img, 'Para', $recipient ?? '-', 200, $muted, $ink);
        $this->row($img, 'Fecha', $date, 252, $muted, $ink);
        $this->row($img, 'Código de seguridad', $code ?? '-', 304, $muted, $ink);
        $this->row($img, 'Nro. de operación', $operation ?? '-', 356, $muted, $ink);

        $this->watermark($img);

        imagefilledrectangle($img, 0, self::HEIGHT - 44, self::WIDTH, self::HEIGHT, $footBg);
        $this->center($img, 3, 'Ejemplo generado por PayGuard', self::HEIGHT - 30, $muted);

        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return $png;
    }

    private function row($img, string $label, string $value, int $y, int $labelColor, int $valueColor): void
    {
        imagestring($img, 3, 40, $y, $this->bytes($label), $labelColor);
        imagestring($img, 4, 40, $y + 18, $this->bytes($value), $valueColor);
    }

    private function center($img, int $font, string $text, int $y, int $color): void
    {
        $bytes = $this->bytes($text);
        $x = (int) ((self::WIDTH - imagefontwidth($font) * strlen($bytes)) / 2);

        imagestring($img, $font, max($x, 8), $y, $bytes, $color);
    }

    private function watermark($img): void
    {
        $word = 'EJEMPLO';
        $small = imagecreatetruecolor(imagefontwidth(5) * strlen($word), imagefontheight(5));
        $paper = imagecolorallocate($small, 255, 255, 255);
        $paint = imagecolorallocate($small, 203, 213, 225);

        imagefilledrectangle($small, 0, 0, imagesx($small), imagesy($small), $paper);
        imagestring($small, 5, 0, 0, $word, $paint);
        imagecolortransparent($small, $paper);

        $width = imagesx($small) * 4;
        $height = imagesy($small) * 4;

        imagecopyresampled(
            $img, $small,
            (int) ((self::WIDTH - $width) / 2), 448,
            0, 0,
            $width, $height,
            imagesx($small), imagesy($small)
        );
        imagedestroy($small);
    }

    private function bytes(string $text): string
    {
        return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    }
}
