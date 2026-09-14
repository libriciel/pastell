<?php

declare(strict_types=1);

namespace Pastell\Tests\Process;

use Pastell\Process\SymfonyCommandRunner;
use PHPUnit\Framework\TestCase;

class SymfonyCommandRunnerTest extends TestCase
{
    private SymfonyCommandRunner $runner;

    protected function setUp(): void
    {
        $this->runner = new SymfonyCommandRunner();
    }

    public static function commandProvider(): \Generator
    {
        yield 'success exits 0 with no output' => [
            'command' => ['true'],
            'expectedExitCode' => 0,
            'expectedStdout' => '',
            'expectedStderr' => '',
        ];
        yield 'stdout is captured' => [
            'command' => ['printf', 'hello'],
            'expectedExitCode' => 0,
            'expectedStdout' => 'hello',
            'expectedStderr' => '',
        ];
        yield 'failing command exits non-zero' => [
            'command' => ['false'],
            'expectedExitCode' => 1,
            'expectedStdout' => '',
            'expectedStderr' => '',
        ];
        yield 'stderr is captured on failure' => [
            'command' => ['ls', '/nonexistent_path'],
            'expectedExitCode' => 1,
            'expectedStdout' => '',
            'expectedStderr' => 'ls: /nonexistent_path: No such file or directory',
        ];
        yield 'shell metacharacters are literal arguments' => [
            'command' => ['echo', 'a; echo b'],
            'expectedExitCode' => 0,
            'expectedStdout' => 'a; echo b',
            'expectedStderr' => '',
        ];
        yield 'missing binary is reported as failure' => [
            'command' => ['/no/such/binary'],
            'expectedExitCode' => 127,
            'expectedStdout' => '',
            'expectedStderr' => 'sh: exec: line 0: /no/such/binary: not found',
        ];
    }

    /**
     * @dataProvider commandProvider
     */
    public function testRun(
        array $command,
        ?int $expectedExitCode,
        string $expectedStdout,
        string $expectedStderr,
    ): void {
        $result = $this->runner->run($command);

        self::assertSame($expectedExitCode, $result->exitCode);
        self::assertSame($expectedExitCode === 0, $result->isSuccessful());
        self::assertSame($expectedStdout, trim($result->stdout));
        self::assertSame($expectedStderr, trim($result->stderr));
    }
}
