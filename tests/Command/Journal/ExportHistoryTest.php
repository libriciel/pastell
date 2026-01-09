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
        if (isset($this->TmpFolder, $this->tmp_folder)) {
            $this->TmpFolder->delete($this->tmp_folder);
        }
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

    /**
     * @throws Exception
     */
    public function testExportWithPreuveAndIdDFilter(): void
    {
        $preuveTsrBinary = file_get_contents(__DIR__ . '/fixtures/preuve.tsr');
        $preuveBase64Expected = trim((string)file_get_contents(__DIR__ . '/fixtures/preuve_base64.txt'));
        $preuveTexteExpected = file_get_contents(__DIR__ . '/fixtures/preuve_texte.txt');

        // Créer un fichier temporaire pour le test
        $tmpFolder = new TmpFolder();
        $tmp = $tmpFolder->create();
        $outFile = $tmp . '/test_export_preuve.csv';

        // Créer les mocks
        $sqlQuery = $this->createMock(SQLQuery::class);
        // Mock prepareAndExecute: vérifier que la requête contient les bons éléments
        $sqlQuery->expects($this->once())
            ->method('prepareAndExecute')
            ->with(
                $this->stringContains('SELECT jh.*, d.titre'),
                $this->callback(function ($params) {
                    // Vérifier les paramètres: ['2025-01-01', '2025-12-31', 'bcd28d74-4669-4b4d-a481-99f3e41fa9a9']
                    return count($params) === 3
                        && $params[0] === '2025-01-01'
                        && $params[1] === '2025-12-31'
                        && $params[2] === 'bcd28d74-4669-4b4d-a481-99f3e41fa9a9';
                })
            );

        // Mock hasMoreResult: retourne true pour la première ligne, puis false
        $sqlQuery->expects($this->exactly(2))
            ->method('hasMoreResult')
            ->willReturnOnConsecutiveCalls(true, false);

        // Mock fetch: retourne une ligne de données correspondant au fixture CSV
        $sqlQuery->expects($this->once())
            ->method('fetch')
            ->willReturn([
                'id_j' => '7304',
                'type' => '7',
                'id_e' => '1',
                'id_u' => '1',
                'id_d' => 'bcd28d74-4669-4b4d-a481-99f3e41fa9a9',
                'action' => 'Consulté',
                'message' => ' admin a consulté le document iparapheur_historique.xml',
                'date' => '2025-08-28 17:34:35',
                'preuve' => $preuveTsrBinary, // Données binaires brutes (pas base64)
                'date_horodatage' => '2025-08-28 17:34:35',
                'message_horodate' => '7 - 1 - 1 - bcd28d74-4669-4b4d-a481-99f3e41fa9a9 - Consulté -  admin a consulté le document iparapheur_historique.xml - 2025-08-28 17:34:35 - ls-document-pdf',
                'document_type' => 'ls-document-pdf',
                'titre' => '0828-pdf-rest-parapheur-rejet',
                'denomination' => 'Libriciel',
                'nom' => 'admin',
                'prenom' => '',
                'siren' => '491011698',
            ]);

        // Utiliser le vrai CSVoutput pour générer le CSV
        $csvOutput = $this->getObjectInstancier()->getInstance(CSVoutput::class);

        // Créer la commande avec les mocks
        $command = new ExportHistory($sqlQuery, $csvOutput);
        $tester = new CommandTester($command);

        // Exécuter la commande
        $status = $tester->execute([
            'date_debut'  => '01/01/2025',
            'date_fin'    => '31/12/2025',
            'output_path' => $outFile,
            '--with_preuve' => true,
            '--id_d' => 'bcd28d74-4669-4b4d-a481-99f3e41fa9a9',
        ]);

        // Vérifications
        self::assertSame(Command::SUCCESS, $status);
        // Lire le contenu du CSV généré
        $content = file_get_contents($outFile);
        self::assertStringContainsString('7304', $content);
        self::assertStringContainsString('bcd28d74-4669-4b4d-a481-99f3e41fa9a9', $content);
        self::assertStringContainsString('Libriciel', $content);

        // Extraire la ligne CSV (en ignorant le header)
        $lines = explode("\n", $content);
        self::assertCount(3, $lines); // Header + 1 ligne de données + ligne vide

        // Parser la ligne de données (ligne 2, index 1)
        $dataLine = str_getcsv($lines[1], escape: '');
        self::assertSame(
            $preuveBase64Expected,
            $dataLine[8],
            'Le contenu du champ "preuve tsr (base64)" doit correspondre exactement au fichier ./fixtures/preuve_base64.txt'
        );

        // Vérifier que le base64 peut être décodé et correspond au binaire
        $preuveDecoded = base64_decode($dataLine[8], true);
        self::assertSame($preuveTsrBinary, $preuveDecoded, 'La preuve décodée doit correspondre au fichier TSR original');

        // Vérifier que la commande openssl ts peut valider la preuve et retourne le contenu attendu
        $tsrTempFile = $tmp . '/preuve_test.tsr';
        file_put_contents($tsrTempFile, $preuveDecoded);
        // Exécuter la commande openssl ts pour vérifier le contenu
        $opensslOutput = shell_exec("/usr/bin/openssl ts -reply -in {$tsrTempFile} -text 2>/dev/null");
        self::assertSame($preuveTexteExpected, $opensslOutput, 'La preuve texte doit correspondre au fichier preuve_texte.txt');

        self::assertStringContainsString('Status: Granted', $opensslOutput, 'Le timestamp doit être validé (Status: Granted)');
        self::assertStringContainsString('Policy OID: tsa_policy1', $opensslOutput);
        self::assertStringContainsString('Hash Algorithm: sha256', $opensslOutput);
        self::assertStringContainsString('Serial number: 0x4E', $opensslOutput);
        self::assertStringContainsString('Time stamp: Aug 28 15:34:35 2025 GMT', $opensslOutput);
        self::assertStringContainsString('TSA: DirName:/C=FR/ST=HERAULT/L=MONTPELLIER/O=LIBRICIEL', $opensslOutput);
        self::assertStringContainsString('emailAddress=test@localhost', $opensslOutput);

        $tmpFolder->delete($tmp);
    }
}
