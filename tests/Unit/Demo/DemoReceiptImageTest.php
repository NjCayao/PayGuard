<?php

namespace Tests\Unit\Demo;

use App\Services\Demo\DemoReceiptImage;
use PHPUnit\Framework\TestCase;

class DemoReceiptImageTest extends TestCase
{
    public function test_generates_a_valid_png_with_the_expected_size(): void
    {
        $png = (new DemoReceiptImage)->generate(
            'S/ 50.00',
            'Luz Marina Torres',
            '28 oct 2026, 09:12 am',
            '123',
            'DEMO-1001',
        );

        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);

        $size = getimagesizefromstring($png);

        $this->assertNotFalse($size);
        $this->assertSame(DemoReceiptImage::WIDTH, $size[0]);
        $this->assertSame(DemoReceiptImage::HEIGHT, $size[1]);
        $this->assertSame('image/png', $size['mime']);
    }

    public function test_generates_without_optional_fields(): void
    {
        $png = (new DemoReceiptImage)->generate('S/ 79.00', null, '28 oct 2026, 12:32 pm', null, null);

        $this->assertStringStartsWith("\x89PNG", $png);
        $this->assertNotFalse(getimagesizefromstring($png));
    }
}
