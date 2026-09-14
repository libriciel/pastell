<?php

namespace Pastell\Tests\Service;

use InvalidArgumentException;
use ParagonIE\Halite\Alerts\CannotPerformOperation;
use ParagonIE\Halite\Alerts\InvalidDigestLength;
use ParagonIE\Halite\Alerts\InvalidKey;
use ParagonIE\Halite\Alerts\InvalidMessage;
use ParagonIE\Halite\Alerts\InvalidSalt;
use ParagonIE\Halite\Alerts\InvalidSignature;
use ParagonIE\Halite\Alerts\InvalidType;
use Pastell\Service\Crypto;
use PHPUnit\Framework\TestCase;
use UnrecoverableException;

class CryptoTest extends TestCase
{
    public static function messageAndPasswordProvider(): iterable
    {
        yield ['', 'empty'];
        yield ['my clear message to encrypt', 'empty'];
        yield ['', 'the password'];
        yield['my clear message to encrypt', 'the password'];
        yield [
            "&é-'è&(É)àç,;:ɐ ɐuƃɐ𝓾𝓲𝓬𝓴;",
            'kè͚̮̺̪̹̱̤ ̖t̝͕̳̣̻̪͞h̼͓̲̦̳̘̲e͇̣̰̦̬͎ ̢̼̻̱̘h͚͎͙̜̣̲ͅi̦̲̣̰̤v̻͍e̺̭̳̪̰-l ʇn ʇunpᴉpᴉɔuᴉ ɹodɯǝʇ poɯsn'
        ];
    }

    /**
     * @dataProvider messageAndPasswordProvider
     * @throws CannotPerformOperation
     * @throws InvalidDigestLength
     * @throws InvalidKey
     * @throws InvalidMessage
     * @throws InvalidSalt
     * @throws InvalidSignature
     * @throws InvalidType
     * @throws UnrecoverableException
     */
    public function testEncryptDecrypt(string $clearText, string $password): void
    {
        $crypto = new Crypto();
        $encrypted = $crypto->encrypt($clearText, $password);
        $this->assertSame($clearText, $crypto->decrypt($encrypted, $password));
    }

    /**
     * @throws CannotPerformOperation
     * @throws InvalidDigestLength
     * @throws InvalidKey
     * @throws InvalidMessage
     * @throws InvalidSalt
     * @throws InvalidSignature
     * @throws InvalidType
     * @throws UnrecoverableException
     */
    public function testDecryptWrongPassword(): void
    {
        $crypto = new Crypto();
        $encrypted = $crypto->encrypt('text', 'pass');

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Le mot de passe est incorrect');

        $crypto->decrypt($encrypted, 'not pass');
    }

    /**
     * @throws CannotPerformOperation
     * @throws InvalidDigestLength
     * @throws InvalidKey
     * @throws InvalidSalt
     * @throws InvalidSignature
     * @throws InvalidType
     * @throws UnrecoverableException
     */
    public function testDecryptWrongMessage(): void
    {
        $crypto = new Crypto();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le message est incorrect.');

        $crypto->decrypt('message', 'pass');
    }
}
