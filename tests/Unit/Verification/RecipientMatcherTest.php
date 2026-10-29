<?php

namespace Tests\Unit\Verification;

use App\Services\Verification\RecipientMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecipientMatcherTest extends TestCase
{
    #[DataProvider('matchingNames')]
    public function test_matching_names(string $screenshot): void
    {
        $this->assertTrue(RecipientMatcher::matches($screenshot, 'Nilson Jhonny Cayao Fernandez'));
    }

    public static function matchingNames(): array
    {
        return [
            'yape to yape truncated' => ['Nilson Cay**'],
            'bcp mobile banking' => ['Nilson J Cayao F'],
            'first name and surname' => ['Nilson Cayao'],
            'without second surname' => ['Nilson Jhonny Cayao'],
            'uppercase with dots' => ['NILSON J. CAYAO F.'],
            'exact' => ['Nilson Jhonny Cayao Fernandez'],
        ];
    }

    #[DataProvider('otherNames')]
    public function test_other_names_do_not_match(string $screenshot): void
    {
        $this->assertFalse(RecipientMatcher::matches($screenshot, 'Nilson Jhonny Cayao Fernandez'));
    }

    public static function otherNames(): array
    {
        return [
            'another person' => ['Maria Lopez'],
            'same first name only' => ['Nilson Perez'],
            'wrong last initial' => ['Nilson J Cayao G'],
            'not anchored at first name' => ['Jhonny Fernandez'],
            'single word' => ['Nilson'],
        ];
    }

    public function test_null_or_blank_never_matches(): void
    {
        $this->assertFalse(RecipientMatcher::matches(null, 'Nilson Jhonny Cayao Fernandez'));
        $this->assertFalse(RecipientMatcher::matches('Nilson Cayao', null));
        $this->assertFalse(RecipientMatcher::matches('', 'Nilson Jhonny Cayao Fernandez'));
    }
}
