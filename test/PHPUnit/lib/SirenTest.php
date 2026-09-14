<?php

use PHPUnit\Framework\TestCase;

class SirenTest extends TestCase
{
    private $siren;

    protected function setUp(): void
    {
        $this->siren = new Siren();
    }

    public static function sirenProvider(): iterable
    {
        yield ['493587273', true];
        yield ['', false];
        yield ['1234', false];
        yield ['493587274', false];
        yield ['ABCDEFGHI', false];
    }

    /**
     * @param string $siren
     * @param bool $isValid
     * @dataProvider sirenProvider
     */
    public function testSiren(string $siren, bool $isValid)
    {
        $this->assertSame($isValid, $this->siren->isValid($siren));
    }

    public function testGenerate()
    {
        $this->assertTrue($this->siren->isValid($this->siren->generate()));
    }
}
