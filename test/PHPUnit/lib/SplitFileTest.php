<?php

class SplitFileTest extends PHPUnit\Framework\TestCase
{
    private function getSplitFile(): SplitFile
    {
        $logger = new Monolog\Logger('PHPUNIT');
        $logger->pushHandler(new Monolog\Handler\NullHandler());
        return new SplitFile($logger);
    }

    /**
     * @throws Exception
     */
    public function testSplitWith1000Chunks(): void
    {
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();

        $splitFile = $this->getSplitFile();

        file_put_contents($tmp_folder . '/large.bin', str_repeat('x', 1000));

        $chunk_list = $splitFile->split($tmp_folder . '/large.bin', 1, 'chunk');

        static::assertCount(1000, $chunk_list);
    }

    /**
     * @throws Exception
     */
    public function testSplit(): void
    {
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();

        file_put_contents($tmp_folder . '/not-in-chunk', 'foo');

        $splitFile = $this->getSplitFile();

        copy(__DIR__ . '/fixtures/test.zip', $tmp_folder . '/test.zip');

        $chunk_list = $splitFile->split($tmp_folder . '/test.zip', 500, 'chunk');

        static::assertEquals(['chunkaaaaaa', 'chunkaaaaab'], $chunk_list);
        static::assertFileExists($tmp_folder . '/chunkaaaaaa');
        static::assertFileExists($tmp_folder . '/chunkaaaaab');
        static::assertEquals(500, filesize($tmp_folder . '/chunkaaaaaa'));
        static::assertEquals(315, filesize($tmp_folder . '/chunkaaaaab'));
    }
}
