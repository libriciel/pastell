<?php

class UtilTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider number_format_fr_provider
     */
    public function test_number_format_fr($number_to_test, $expected_result)
    {
        $this->assertEquals($expected_result, number_format_fr($number_to_test));
    }

    public static function number_format_fr_provider()
    {
        return [
            [0,"0"],
            [42,"42"],
            [24768980,"24 768 980"]
        ];
    }

    /**
     * @dataProvider date_iso_to_fr_provider
     */
    public function test_date_iso_to_fr($date_iso, $expected_date_fr)
    {
        $this->assertEquals($expected_date_fr, date_iso_to_fr($date_iso));
    }

    public static function date_iso_to_fr_provider()
    {
        return [
            ['',''],
            ['2018-12-10', '10/12/2018']
        ];
    }
}
