<?php

declare(strict_types=1);

namespace Pastell\Tests\Security;

use Pastell\Security\LibricielFeedbackReader;
use PHPUnit\Framework\TestCase;
use StaticWrapper;
use TmpFolder;

class LibricielFeedbackReaderTest extends TestCase
{
    private const CRITICAL_STATE = '{"version_installe":{"etat":"critique"}}';
    private string $testDirectory;
    private StaticWrapper $memoryCache;
    private TmpFolder $tmpFolder;

    /**
     * @throws \Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpFolder = new TmpFolder();
        $this->testDirectory = $this->tmpFolder->create();
        $this->memoryCache = new StaticWrapper();
        StaticWrapper::$memory = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->tmpFolder->delete($this->testDirectory);
    }

    private function createFeedbackFile(string $content): void
    {
        file_put_contents(
            $this->testDirectory . DIRECTORY_SEPARATOR . 'libriciel-feedback.json',
            $content
        );
    }

    public function getLibricielFeedbackReader(): LibricielFeedbackReader
    {
        return new LibricielFeedbackReader(
            true,
            $this->testDirectory,
            $this->memoryCache
        );
    }

    public static function libricielFeedbackFileProvider(): \Generator
    {
        yield ['', false];
        yield ['not a json file', false];
        yield ['{}', false];
        yield ['{"field":"test"}', false];
        yield ['{"version_installe":"test"}', false];
        yield ['{"version_installe":"{"field":"critique"}"}', false];
        yield [self::CRITICAL_STATE, true];
        yield ['{"version_installe":{"etat":"caduque"}}', false];
        yield ['{"version_installe":{"etat":"en_cours"}}', false];
    }

    /**
     * @dataProvider libricielFeedbackFileProvider
     */
    public function testHasSecurityAlert(string $fileContent, bool $hasAlert): void
    {
        $this->createFeedbackFile($fileContent);
        static::assertSame($hasAlert, $this->getLibricielFeedbackReader()->hasSecurityAlert());
    }

    public function testCache(): void
    {
        $this->createFeedbackFile('');
        $reader = $this->getLibricielFeedbackReader();
        self::assertFalse($reader->hasSecurityAlert());
        $this->createFeedbackFile(self::CRITICAL_STATE);
        self::assertFalse($reader->hasSecurityAlert());
    }
}
