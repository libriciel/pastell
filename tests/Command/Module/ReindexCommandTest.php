<?php

declare(strict_types=1);

namespace Pastell\Tests\Command\Module;

use DocumentControler;
use Pastell\Command\Module\Reindex;
use PastellTestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ReindexCommandTest extends PastellTestCase
{
    public function testOk(): void
    {
        $svc = $this->createMock(DocumentControler::class);
        $svc->expects(self::once())
            ->method('reindex')
            ->with('flux-demo', 'champX', 10, 50);

        $cmd = new Reindex($svc);
        $tester = new CommandTester($cmd);

        $code = $tester->execute([
            'type_de_document' => 'flux-demo',
            'champ_a_reindexer' => 'champX',
            'offset' => '10',
            'limit' => '50',
        ]);

        self::assertSame(Command::SUCCESS, $code);
        $out = $tester->getDisplay();
        self::assertStringContainsString('Réindexation lancée.', $out);
        self::assertStringContainsString("Type='flux-demo'", $out);
        self::assertStringContainsString("Champ='champX'", $out);
        self::assertStringContainsString('Offset=10', $out);
        self::assertStringContainsString('Limit=50', $out);
    }

    public function testFail(): void
    {
        $svc = $this->createMock(DocumentControler::class);
        $svc->expects(self::once())
            ->method('reindex')
            ->with('flux-demo', 'champX', 0, -1)
            ->willThrowException(new RuntimeException('échec de test'));

        $cmd = new Reindex($svc);
        $tester = new CommandTester($cmd);

        $code = $tester->execute([
            'type_de_document' => 'flux-demo',
            'champ_a_reindexer' => 'champX',
        ]);

        self::assertSame(Command::FAILURE, $code);
        $out = $tester->getDisplay();
        self::assertStringContainsString('Erreur lors de la réindexation', $out);
        self::assertStringContainsString('échec de test', $out);
    }
}
