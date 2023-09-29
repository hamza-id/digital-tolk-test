<?php

namespace Tests\Feature;

use DTApi\Helpers\TeHelper;
use Tests\TestCase;

class CheckExpiry extends TestCase
{
    /**
     * @dataProvider expirationData
     */
    public function testWillExpireAt($dueTime, $createdAt, $expectedResult)
    {
        $result = TeHelper::willExpireAt($dueTime, $createdAt);
        $this->assertEquals($expectedResult, $result);
    }

    public function expirationData()
    {
        //due time, created at, final result
        return [
            // Test case 1: Difference <= 90
            ['2023-09-28 12:00:00', '2023-09-28 11:00:00', '2023-09-28 12:00:00'],

            // Test case 2: 90 < Difference <= 24
            ['2023-09-28 14:00:00', '2023-09-28 11:00:00', '2023-09-28 12:30:00'],

            // Test case 3: 24 < Difference <= 72
            ['2023-09-29 12:00:00', '2023-09-28 10:00:00', '2023-09-28 18:00:00'],

            // Test case 4: Difference > 72
            ['2023-09-30 12:00:00', '2023-09-28 10:00:00', '2023-09-30 12:00:00'],
        ];
    }
}
