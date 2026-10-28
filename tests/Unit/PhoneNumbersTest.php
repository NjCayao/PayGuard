<?php

namespace Tests\Unit;

use App\Services\PhoneNumbers;
use PHPUnit\Framework\TestCase;

class PhoneNumbersTest extends TestCase
{
    public function test_normalize_keeps_only_digits(): void
    {
        $this->assertSame('51999888777', PhoneNumbers::normalize('+51 999-888 777'));
    }

    public function test_normalize_keeps_plain_digits(): void
    {
        $this->assertSame('51999888777', PhoneNumbers::normalize('51999888777'));
    }

    public function test_normalize_handles_null(): void
    {
        $this->assertSame('', PhoneNumbers::normalize(null));
    }
}
