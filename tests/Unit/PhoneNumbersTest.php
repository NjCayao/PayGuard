<?php

namespace Tests\Unit;

use App\Services\PhoneNumbers;
use PHPUnit\Framework\TestCase;

class PhoneNumbersTest extends TestCase
{
    public function test_normalize_keeps_only_digits(): void
    {
        $this->assertSame('51982226835', PhoneNumbers::normalize('+51 982-226 835'));
    }

    public function test_normalize_keeps_plain_digits(): void
    {
        $this->assertSame('51982226835', PhoneNumbers::normalize('51982226835'));
    }

    public function test_normalize_handles_null(): void
    {
        $this->assertSame('', PhoneNumbers::normalize(null));
    }
}
