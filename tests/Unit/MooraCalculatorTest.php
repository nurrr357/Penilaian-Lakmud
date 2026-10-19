<?php

namespace Tests\Unit;

use App\Services\MooraCalculator;
use PHPUnit\Framework\TestCase;

class MooraCalculatorTest extends TestCase
{
    public function test_cost_normalization_and_competition_ranking(): void
    {
        $result = (new MooraCalculator)->calculate([1 => [0, 150, 150, 150, 150, 150], 2 => [1, 300, 300, 300, 300, 300]]);
        $this->assertEqualsWithDelta(0.4024922359, $result['rows'][1]['yi'], 1e-8);
        $this->assertEqualsWithDelta(0.7049844719, $result['rows'][2]['yi'], 1e-8);
        $this->assertSame(1, $result['rows'][2]['rank']);
        $ties = (new MooraCalculator)->calculate([1 => [0, 300, 300, 300, 300, 300], 2 => [0, 300, 300, 300, 300, 300], 3 => [0, 150, 150, 150, 150, 150]]);
        $this->assertSame([1, 1, 3], array_column($ties['rows'], 'rank'));
        $this->assertSame(0.0, $ties['divisors'][0]);
    }

    public function test_zero_benefit_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new MooraCalculator)->calculate([[0, 0, 150, 150, 150, 150]]);
    }
}
