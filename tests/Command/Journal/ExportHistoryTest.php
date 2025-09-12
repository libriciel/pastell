<?php

declare(strict_types=1);

namespace Pastell\Tests\Command\Journal;

use CSVoutput;
use Exception;
use Journal;
use Pastell\Command\Journal\ExportHistory;
use PastellTestCase;
use SQLQuery;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TmpFolder;

final class ExportHistoryTest extends PastellTestCase
{
    private CommandTester $tester;
    private string $outFile;
    private TmpFolder $TmpFolder;
    private string $tmp_folder;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $command = new ExportHistory(
            $this->getObjectInstancier()->getInstance(SQLQuery::class),
            $this->getObjectInstancier()->getInstance(CSVoutput::class)
        );

        $this->TmpFolder = new TmpFolder();
        $this->tmp_folder = $this->TmpFolder->create();
        $this->tester  = new CommandTester($command);
        $this->outFile = $this->tmp_folder . '/pastell-export-' . uniqid('', true) . '.csv';
        @unlink($this->outFile);

        $id_j = $this->getJournal()->add(Journal::TEST, 0, '', '', 'foo');
        $sql = 'UPDATE journal SET date=? WHERE id_j=?';
        self::getSQLQuery()->query($sql, '2025-03-01', $id_j);
        $sql_insert = 'INSERT INTO journal_historique SELECT * FROM journal WHERE id_j=?';
        self::getSQLQuery()->queryOne($sql_insert, $id_j);
    }

    public function __destruct()
    {
        $this->TmpFolder->delete($this->tmp_folder);
    }

    /**
     * @throws Exception
     */
    public function testInvalidDatesReturnInvalid(): void
    {
        $sql = $this->createMock(SQLQuery::class);
        $csv = $this->createMock(CSVoutput::class);

        $command = new ExportHistory($sql, $csv);
        $tester  = new CommandTester($command);

        $status = $tester->execute([
            'date_debut'  => '2025-01-01',
            'date_fin'    => '31-12-2025',
            'output_path' => $this->outFile,
        ]);

        self::assertSame(Command::INVALID, $status);
        self::assertStringContainsString('Format attendu : JJ/MM/AAAA', $tester->getDisplay());
    }

    public function testRunCreatesFile(): void
    {
        $status = $this->tester->execute([
            'date_debut'  => '01/01/2025',
            'date_fin'    => '31/12/2025',
            'output_path' => $this->outFile,
        ]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertFileExists($this->outFile);
        self::assertIsReadable($this->outFile);

        $content = file_get_contents($this->outFile);
        self::assertIsString($content);
        self::assertStringContainsString('foo', $content);
    }
}
