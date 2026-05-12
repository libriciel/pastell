<?php

declare(strict_types=1);

use Pastell\Process\SymfonyCommandRunner;
use PHPUnit\Framework\TestCase;

class OpensslTSWrapperTest extends TestCase
{
    private const TIMESTAMP_KEY_FILENAME = 'timestamp-key.pem';
    private const TIMESTAMP_CERTIFICATE_FILENAME = 'timestamp-certificate.pem';
    private OpensslTSWrapper $opensslTSWrapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->opensslTSWrapper = new OpensslTSWrapper(OPENSSL_PATH, new SymfonyCommandRunner());
    }

    public function testGetTimestampQuery(): void
    {
        $timestamp_query = $this->opensslTSWrapper->getTimestampQuery('toto');
        static::assertNotEmpty($timestamp_query);
    }

    public function testGetTimestampQueryString(): void
    {
        $this->opensslTSWrapper->setHashAlgorithm('sha256');
        $timestamp_query = $this->opensslTSWrapper->getTimestampQuery('toto');
        $timestamp_query_string = $this->opensslTSWrapper->getTimestampQueryString($timestamp_query);
        static::assertMatchesRegularExpression('#Hash Algorithm: sha256#', $timestamp_query_string);
    }

    public function testCreateTimestampReply(): void
    {
        $timestamp_query = $this->opensslTSWrapper->getTimestampQuery('toto');
        chdir(__DIR__ . '/fixtures/');
        $timestamp_reply = $this->opensslTSWrapper->createTimestampReply(
            $timestamp_query,
            __DIR__ . '/fixtures/' . self::TIMESTAMP_CERTIFICATE_FILENAME,
            __DIR__ . '/fixtures/' . self::TIMESTAMP_KEY_FILENAME,
            "",
            __DIR__ . '/fixtures/openssl-tsa.cnf'
        );
        $timestamp_reply_string = $this->opensslTSWrapper->getTimestampReplyString($timestamp_reply);
        static::assertMatchesRegularExpression('#Policy OID: tsa_policy1#', $timestamp_reply_string);
    }

    public function testVerify(): void
    {
        $this->opensslTSWrapper->setHashAlgorithm('sha256');
        $timestamp_query = $this->opensslTSWrapper->getTimestampQuery('toto');
        chdir(__DIR__ . '/fixtures/');
        $timestamp_reply = $this->opensslTSWrapper->createTimestampReply(
            $timestamp_query,
            __DIR__ . '/fixtures/' . self::TIMESTAMP_CERTIFICATE_FILENAME,
            __DIR__ . '/fixtures/' . self::TIMESTAMP_KEY_FILENAME,
            '',
            __DIR__ . '/fixtures/openssl-tsa.cnf'
        );
        static::assertTrue($this->opensslTSWrapper->verify(
            'toto',
            $timestamp_reply,
            __DIR__ . '/fixtures/' . self::TIMESTAMP_CERTIFICATE_FILENAME,
            __DIR__ . '/fixtures/' . self::TIMESTAMP_CERTIFICATE_FILENAME,
            __DIR__ . '/fixtures/openssl-tsa.cnf'
        ));
    }

    public function testVerifyFailed(): void
    {
        $timestamp_reply = 'not a timestamp reply';
        static::assertFalse($this->opensslTSWrapper->verify(
            'toto',
            $timestamp_reply,
            __DIR__ . '/fixtures/' . self::TIMESTAMP_CERTIFICATE_FILENAME,
            __DIR__ . '/fixtures/' . self::TIMESTAMP_CERTIFICATE_FILENAME,
            __DIR__ . '/fixtures/openssl-tsa.cnf'
        ));
        static::assertMatchesRegularExpression(
            '#Verification: FAILED#',
            $this->opensslTSWrapper->getLastError()
        );
    }

    public function testAllSha1(): void
    {
        $timestamp_query = $this->opensslTSWrapper->getTimestampQuery('toto');
        chdir(__DIR__ . '/fixtures/');
        $timestamp_reply = $this->opensslTSWrapper->createTimestampReply(
            $timestamp_query,
            __DIR__ . '/fixtures/' . self::TIMESTAMP_CERTIFICATE_FILENAME,
            __DIR__ . '/fixtures/' . self::TIMESTAMP_KEY_FILENAME,
            '',
            __DIR__ . '/fixtures/openssl-tsa.cnf'
        );
        static::assertTrue($this->opensslTSWrapper->verify(
            'toto',
            $timestamp_reply,
            __DIR__ . '/fixtures/' . self::TIMESTAMP_CERTIFICATE_FILENAME,
            __DIR__ . '/fixtures/' . self::TIMESTAMP_CERTIFICATE_FILENAME,
            __DIR__ . '/fixtures/openssl-tsa.cnf'
        ));
    }
}
