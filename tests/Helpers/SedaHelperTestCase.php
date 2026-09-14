<?php

declare(strict_types=1);

namespace Pastell\Tests\Helpers;

use Pastell\Helpers\SedaHelper;
use PHPUnit\Framework\TestCase;
use UnrecoverableException;

abstract class SedaHelperTestCase extends TestCase
{
    abstract public function sedaHelper(): SedaHelper;
    abstract public static function atrProviderOk(): iterable;
    abstract public static function atrProviderKo(): iterable;

    public static function ackProvider(): iterable
    {
        yield 'asalae' => [
            __DIR__ . '/fixtures/ack_asalae.xml',
            [
                'MessageIdentifier' => 'FRAD000_ACK_14',
                'MessageReceivedIdentifier' => '15ef78ef665a8777c33d1125783707f8dfb190f82869dc9248e46c5ed396d70b_1542893421',
            ]
        ];
    }

    public static function atrProvider(): iterable
    {
        yield from static::atrProviderOk();
        yield from static::atrProviderKo();
    }

    /**
     * @dataProvider atrProvider
     * @throws UnrecoverableException
     */
    public function testGetTransfertIdFromAtr(string $filepath, array $expected): void
    {
        $xml = \simplexml_load_string(\file_get_contents($filepath));
        static::assertSame($expected['MessageIdentifier'], $this->sedaHelper()->getTransfertIdFromAtr($xml));
    }

    /**
     * @dataProvider atrProvider
     * @throws UnrecoverableException
     */
    public function testGetAtrID(string $filepath, array $expected): void
    {
        $xml = \simplexml_load_string(\file_get_contents($filepath));
        static::assertSame($expected['MessageRequestIdentifier'], $this->sedaHelper()->getAtrID($xml));
    }

    /**
     * @dataProvider atrProviderOk
     * @throws UnrecoverableException
     */
    public function testGetSAEArchivalIdentifierFromAtr(string $filepath, array $expected): void
    {
        $xml = \simplexml_load_string(\file_get_contents($filepath));
        static::assertSame(
            $expected['ArchiveUnitIdentifier'],
            $this->sedaHelper()->getSAEArchivalIdentifierFromAtr($xml)
        );
    }

    /**
     * @dataProvider atrProvider
     * @throws UnrecoverableException
     */
    public function testGetComment(string $filepath, array $expected): void
    {
        $xml = \simplexml_load_string(\file_get_contents($filepath));
        static::assertSame($expected['Comment'], $this->sedaHelper()->getComment($xml));
    }

    /**
     * @dataProvider ackProvider
     * @throws UnrecoverableException
     */
    public function testGetTransfertIdFromAck(string $filepath, array $expected): void
    {
        $xml = \simplexml_load_string(\file_get_contents($filepath));
        static::assertSame($expected['MessageReceivedIdentifier'], $this->sedaHelper()->getTransfertIdFromAck($xml));
    }

    /**
     * @dataProvider ackProvider
     * @throws UnrecoverableException
     */
    public function testGetAckID(string $filepath, array $expected): void
    {
        $xml = \simplexml_load_string(\file_get_contents($filepath));
        static::assertSame($expected['MessageIdentifier'], $this->sedaHelper()->getAckID($xml));
    }
}
