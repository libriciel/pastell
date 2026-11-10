<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Connecteur\Frequence;

use ConnecteurEntiteSQL;
use ConnecteurFrequenceSQL;
use Generator;
use Job;
use Pastell\Service\Connecteur\Frequence\ConnecteurFrequenceActionType;
use Pastell\Service\Connecteur\Frequence\ConnecteurFrequenceData;
use Pastell\Service\Connecteur\Frequence\ConnecteurFrequenceService;
use Pastell\Service\Connecteur\Frequence\ConnecteurFrequenceType;
use PastellTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use UnrecoverableException;

final class ConnecteurFrequenceServiceTest extends PastellTestCase
{
    private const DEFAULT_FREQUENCE = 'DEFAULT_FREQUENCE';

    private ConnecteurFrequenceService $connecteurFrequenceService;
    private ConnecteurFrequenceData $connecteurFrequenceData;
    private int $id_cf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connecteurFrequenceService = $this->getObjectInstancier()->getInstance(ConnecteurFrequenceService::class);
        $this->connecteurFrequenceData = new ConnecteurFrequenceData(
            type_connecteur: ConnecteurFrequenceType::ENTITE->value,
            famille_connecteur: 'signature',
            id_connecteur: 'i-parapheur',
            id_ce: 1,
            action_type: ConnecteurFrequenceActionType::DOCUMENT->value,
            type_document: 'actes-generique',
            action: 'verif-signature',
            expression: '10',
            id_verrou: 'SIGNATURE',
        );
        $this->id_cf = $this->connecteurFrequenceService->save($this->connecteurFrequenceData);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testSaveAndGet(): void
    {
        $connecteurFrequenceData = $this->connecteurFrequenceService->get($this->id_cf);

        self::assertSame($this->id_cf, $connecteurFrequenceData->id_cf);
        self::assertSame(ConnecteurFrequenceType::ENTITE->value, $connecteurFrequenceData->type_connecteur);
        self::assertSame(1, $connecteurFrequenceData->id_ce);
        self::assertSame('actes-generique', $connecteurFrequenceData->type_document);
        self::assertSame('10', $connecteurFrequenceData->expression);
        self::assertSame('SIGNATURE', $connecteurFrequenceData->id_verrou);
    }

    public function testDelete(): void
    {
        $this->connecteurFrequenceService->delete($this->id_cf);

        self::assertNull($this->connecteurFrequenceService->get($this->id_cf));
    }

    /**
     * @throws UnrecoverableException
     */
    public function testSaveUpdate(): void
    {
        $this->connecteurFrequenceService->save(
            new ConnecteurFrequenceData(id_cf: $this->id_cf, type_connecteur: ConnecteurFrequenceType::GLOBAL->value)
        );

        self::assertSame(
            ConnecteurFrequenceType::GLOBAL->value,
            $this->connecteurFrequenceService->get($this->id_cf)->type_connecteur
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function testSaveWithoutFamille(): void
    {
        $id_cf = $this->connecteurFrequenceService->save(
            new ConnecteurFrequenceData(
                type_connecteur: ConnecteurFrequenceType::ENTITE->value,
                id_connecteur: 'i-parapheur',
                id_ce: 1,
                action_type: ConnecteurFrequenceActionType::DOCUMENT->value,
                type_document: 'actes-generique',
                action: 'verif-signature',
            )
        );

        self::assertEquals(
            new ConnecteurFrequenceData(id_cf: $id_cf, type_connecteur: ConnecteurFrequenceType::ENTITE->value),
            $this->connecteurFrequenceService->get($id_cf)
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function testSaveConnecteurAction(): void
    {
        $id_cf = $this->connecteurFrequenceService->save(
            new ConnecteurFrequenceData(
                type_connecteur: ConnecteurFrequenceType::ENTITE->value,
                famille_connecteur: 'signature',
                id_ce: 1,
                action_type: ConnecteurFrequenceActionType::CONNECTEUR->value,
                type_document: 'actes-generique',
                action: 'recup',
            )
        );
        $connecteurFrequenceData = $this->connecteurFrequenceService->get($id_cf);

        self::assertNull($connecteurFrequenceData->id_ce);
        self::assertSame('', $connecteurFrequenceData->type_document);
        self::assertSame('recup', $connecteurFrequenceData->action);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testSaveUnknownTypes(): void
    {
        $id_cf = $this->connecteurFrequenceService->save(
            new ConnecteurFrequenceData(
                type_connecteur: 'foo',
                famille_connecteur: 'signature',
                action_type: 'bar',
                type_document: 'actes-generique',
            )
        );
        $connecteurFrequenceData = $this->connecteurFrequenceService->get($id_cf);

        self::assertSame('foo', $connecteurFrequenceData->type_connecteur);
        self::assertSame('bar', $connecteurFrequenceData->action_type);
        self::assertSame('actes-generique', $connecteurFrequenceData->type_document);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testGetNotFound(): void
    {
        self::assertNull($this->connecteurFrequenceService->get(42));
    }

    /**
     * @throws UnrecoverableException
     */
    public function testGetAll(): void
    {
        $all = $this->connecteurFrequenceService->getAll();

        self::assertCount(2, $all);
        self::assertSame(1, $all[0]->id_cf);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testFindNearestExactMatch(): void
    {
        self::assertSame(
            $this->id_cf,
            $this->connecteurFrequenceService->findNearest($this->connecteurFrequenceData)->id_cf
        );
    }

    public function testFindNearestIgnoreCase(): void
    {
        $connecteurFrequenceData = new ConnecteurFrequenceData(
            type_connecteur: ConnecteurFrequenceType::ENTITE->value,
            famille_connecteur: 'Signature',
            id_connecteur: 'I-Parapheur',
            id_ce: 1,
            action_type: ConnecteurFrequenceActionType::DOCUMENT->value,
            type_document: 'Actes-Generique',
            action: 'Verif-Signature',
        );

        self::assertSame($this->id_cf, $this->connecteurFrequenceService->findNearest($connecteurFrequenceData)->id_cf);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testFindNearestNoFrequence(): void
    {
        $this->getObjectInstancier()->getInstance(ConnecteurFrequenceSQL::class)->deleteAll();

        self::assertNull($this->connecteurFrequenceService->findNearest($this->connecteurFrequenceData));
    }

    /**
     * @throws UnrecoverableException
     */
    public function testFindNearestOtherConnecteur(): void
    {
        $connecteurFrequenceData = new ConnecteurFrequenceData(
            type_connecteur: ConnecteurFrequenceType::ENTITE->value,
            famille_connecteur: 'TdT',
            id_connecteur: 's2low',
            id_ce: 42,
            action_type: ConnecteurFrequenceActionType::DOCUMENT->value,
            type_document: 'helios-generique',
            action: 'verif-tdt',
        );

        self::assertSame(
            self::DEFAULT_FREQUENCE,
            $this->connecteurFrequenceService->findNearest($connecteurFrequenceData)->id_verrou
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function testFindNearestOtherDocument(): void
    {
        $connecteurFrequenceData = new ConnecteurFrequenceData(
            type_connecteur: ConnecteurFrequenceType::ENTITE->value,
            famille_connecteur: 'signature',
            id_connecteur: 'i-parapheur',
            id_ce: 1,
            action_type: ConnecteurFrequenceActionType::DOCUMENT->value,
            type_document: 'helios-generique',
            action: 'verif-signature',
        );

        self::assertSame(
            self::DEFAULT_FREQUENCE,
            $this->connecteurFrequenceService->findNearest($connecteurFrequenceData)->id_verrou
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function testFindNearestPartialMatch(): void
    {
        $id_cf = $this->connecteurFrequenceService->save(
            new ConnecteurFrequenceData(
                type_connecteur: ConnecteurFrequenceType::ENTITE->value,
                famille_connecteur: 'SAE',
                action_type: ConnecteurFrequenceActionType::DOCUMENT->value,
                type_document: 'helios-generique',
            )
        );
        $connecteurFrequenceData = new ConnecteurFrequenceData(
            type_connecteur: ConnecteurFrequenceType::ENTITE->value,
            famille_connecteur: 'SAE',
            id_connecteur: 'As@lae',
            id_ce: 1,
            action_type: ConnecteurFrequenceActionType::DOCUMENT->value,
            type_document: 'helios-generique',
            action: 'verif-signature',
        );

        self::assertSame($id_cf, $this->connecteurFrequenceService->findNearest($connecteurFrequenceData)->id_cf);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testFindNearestPartialConnecteur(): void
    {
        $id_cf = $this->connecteurFrequenceService->save(
            new ConnecteurFrequenceData(
                type_connecteur: ConnecteurFrequenceType::ENTITE->value,
                famille_connecteur: 'signature',
            )
        );
        $connecteurFrequenceData = new ConnecteurFrequenceData(
            type_connecteur: ConnecteurFrequenceType::ENTITE->value,
            famille_connecteur: 'signature',
            id_connecteur: 'i-Parapheur',
            id_ce: 1,
            action_type: ConnecteurFrequenceActionType::DOCUMENT->value,
            type_document: 'helios-generique',
            action: 'verif-signature',
        );

        self::assertSame($id_cf, $this->connecteurFrequenceService->findNearest($connecteurFrequenceData)->id_cf);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testGetForConnecteur(): void
    {
        $connecteurFrequenceData = $this->connecteurFrequenceService->getForConnecteur($this->getConnecteurInfo(13));

        self::assertSame(1, $connecteurFrequenceData->id_cf);
        self::assertSame(self::DEFAULT_FREQUENCE, $connecteurFrequenceData->id_verrou);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testGetForConnecteurDefault(): void
    {
        $this->getObjectInstancier()->getInstance(ConnecteurFrequenceSQL::class)->deleteAll();

        $connecteurFrequenceData = $this->connecteurFrequenceService->getForConnecteur($this->getConnecteurInfo(13));

        self::assertNull($connecteurFrequenceData->id_cf);
        self::assertSame(ConnecteurFrequenceService::DEFAULT_ID_VERROU, $connecteurFrequenceData->id_verrou);
        self::assertSame(ConnecteurFrequenceService::DEFAULT_EXPRESSION, $connecteurFrequenceData->expression);
    }

    public function testGlobalConnecteurByFlux(): void
    {
        self::assertSame([], $this->connecteurFrequenceService->getForConnecteurByFlux(['global' => 1]));
    }

    public function testJobDeletedDocument(): void
    {
        $job = new Job();
        $job->type = Job::TYPE_DOCUMENT;
        $job->id_e = 1;
        $job->id_d = 'unknown';
        $job->etat_cible = 'send-tdt';

        self::assertSame(self::DEFAULT_FREQUENCE, $this->connecteurFrequenceService->getForJob($job)->id_verrou);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testGetForFlux(): void
    {
        $this->connecteurFrequenceService->save(
            new ConnecteurFrequenceData(
                type_connecteur: ConnecteurFrequenceType::ENTITE->value,
                famille_connecteur: 'GED',
                action_type: ConnecteurFrequenceActionType::DOCUMENT->value,
                type_document: 'actes-generique',
                id_verrou: 'FREQUENCE_FakeGED',
            )
        );

        self::assertSame(
            self::DEFAULT_FREQUENCE,
            $this->connecteurFrequenceService->getForConnecteurByFlux($this->getConnecteurInfo(3))['actes-generique']->id_verrou
        );
        self::assertSame(
            'FREQUENCE_FakeGED',
            $this->connecteurFrequenceService->getForConnecteurByFlux($this->getConnecteurInfo(5))['actes-generique']->id_verrou
        );
        self::assertSame(
            self::DEFAULT_FREQUENCE,
            $this->connecteurFrequenceService->getForConnecteurByFlux($this->getConnecteurInfo(13))['test']->id_verrou
        );
    }

    public function testNextTryEmpty(): void
    {
        self::assertSame('', $this->connecteurFrequenceService->getNextTry('', 42));
        self::assertSame('', $this->connecteurFrequenceService->getNextTry('0', 42));
    }

    public static function nextTryProvider(): Generator
    {
        yield 'every 10 minutes' => ['10', 42, '2012-06-27 18:23:46', '2012-06-27 18:33:46'];
        yield 'at 2:40' => ['(40 2 * * *)', 42, '2017-04-13 11:48:45', '2017-04-14 02:40:00'];
        yield 'every 10 seconds' => ['10s', 42, '2022-06-14 00:00:00', '2022-06-14 00:00:10'];
        yield '43 times every second' => ['1s X 43', 42, '2022-06-14 00:00:00', '2022-06-14 00:00:01'];
        yield 'first line' => ["1X5\n60X10\n1X25\n42", 4, '2022-06-14 00:00:00', '2022-06-14 00:01:00'];
        yield 'second line' => ["1X5\n60X10\n1X25\n42", 5, '2022-06-14 00:00:00', '2022-06-14 01:00:00'];
        yield 'third line' => ["1X5\n60X10\n1X25\n42", 15, '2022-06-14 00:00:00', '2022-06-14 00:01:00'];
        yield 'last line' => ["1X5\n60X10\n1X25\n42", 500, '2022-06-14 00:00:00', '2022-06-14 00:42:00'];
        yield 'spaces' => ["1 X 5\n60 X 10", 10, '2022-06-14 00:00:00', '2022-06-14 01:00:00'];
    }

    /**
     * @throws UnrecoverableException
     */
    #[DataProvider('nextTryProvider')]
    public function testNextTry(string $expression, int $nb_try, string $relative_date, string $expected): void
    {
        self::assertSame(
            $expected,
            $this->connecteurFrequenceService->getNextTry($expression, $nb_try, $relative_date)
        );
    }

    public function testTooManyTries(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Trop d'essai sur le connecteur");
        $this->connecteurFrequenceService->getNextTry('1 X 10', 11);
    }

    public function testExpressionLabel(): void
    {
        self::assertSame(
            <<<EOT
Toutes les minutes (10 fois)
Toutes les 10 secondes (10 fois)
A (* * * * *) (1 fois)
Toutes les 60 minutes (1 fois)
Suspendre le travail
EOT,
            $this->connecteurFrequenceService->getExpressionLabel(new ConnecteurFrequenceData(expression: "1 X 10\n10s X 10\n(* * * * *) X 1\n60 X 1"))
        );
    }

    public function testExpressionLabelEmpty(): void
    {
        self::assertSame("\n", $this->connecteurFrequenceService->getExpressionLabel(new ConnecteurFrequenceData(expression: '')));
    }

    public static function connecteurLabelProvider(): Generator
    {
        yield [new ConnecteurFrequenceData(), 'Tous les connecteurs'];
        yield [
            new ConnecteurFrequenceData(type_connecteur: ConnecteurFrequenceType::GLOBAL->value),
            '(Global) Tous les connecteurs',
        ];
        yield [
            new ConnecteurFrequenceData(type_connecteur: ConnecteurFrequenceType::ENTITE->value),
            '(Entité) Tous les connecteurs',
        ];
        yield [new ConnecteurFrequenceData(type_connecteur: 'Global'), '(Entité) Tous les connecteurs'];
        yield [
            new ConnecteurFrequenceData(type_connecteur: ConnecteurFrequenceType::ENTITE->value, famille_connecteur: 'signature'),
            '(Entité) signature',
        ];
        yield [
            new ConnecteurFrequenceData(
                type_connecteur: ConnecteurFrequenceType::ENTITE->value,
                famille_connecteur: 'signature',
                id_connecteur: 'i-parapheur',
            ),
            '(Entité) signature:i-parapheur',
        ];
    }

    #[DataProvider('connecteurLabelProvider')]
    public function testConnecteurLabel(ConnecteurFrequenceData $connecteurFrequenceData, string $expected): void
    {
        self::assertSame($expected, $this->connecteurFrequenceService->getConnecteurLabel($connecteurFrequenceData));
    }

    public static function actionLabelProvider(): Generator
    {
        yield [new ConnecteurFrequenceData(), 'Toutes les actions'];
        yield [
            new ConnecteurFrequenceData(action_type: ConnecteurFrequenceActionType::CONNECTEUR->value),
            '(Connecteur) toutes les actions',
        ];
        yield [
            new ConnecteurFrequenceData(action_type: ConnecteurFrequenceActionType::CONNECTEUR->value, action: 'recup-type'),
            '(Connecteur) recup-type',
        ];
        yield [
            new ConnecteurFrequenceData(action_type: ConnecteurFrequenceActionType::DOCUMENT->value),
            '(Document) Tous les types de dossiers',
        ];
        yield [new ConnecteurFrequenceData(action_type: 'toto'), '(Document) Tous les types de dossiers'];
        yield [
            new ConnecteurFrequenceData(
                action_type: ConnecteurFrequenceActionType::DOCUMENT->value,
                type_document: 'actes-generique',
            ),
            '(Document) actes-generique: toutes les actions',
        ];
        yield [
            new ConnecteurFrequenceData(
                action_type: ConnecteurFrequenceActionType::DOCUMENT->value,
                type_document: 'actes-generique',
                action: 'verif-signature',
            ),
            '(Document) actes-generique: verif-signature',
        ];
    }

    #[DataProvider('actionLabelProvider')]
    public function testActionLabel(ConnecteurFrequenceData $connecteurFrequenceData, string $expected): void
    {
        self::assertSame($expected, $this->connecteurFrequenceService->getActionLabel($connecteurFrequenceData));
    }

    public function testInstanceLabel(): void
    {
        self::assertSame('', $this->connecteurFrequenceService->getInstanceLabel(new ConnecteurFrequenceData()));
        self::assertSame(
            'Parapheur [Entité racine]',
            $this->connecteurFrequenceService->getInstanceLabel(
                new ConnecteurFrequenceData(id_ce: 1, libelle: 'Parapheur')
            )
        );
        self::assertSame(
            'Parapheur [Bourg-en-Bresse]',
            $this->connecteurFrequenceService->getInstanceLabel(
                new ConnecteurFrequenceData(id_ce: 1, libelle: 'Parapheur', denomination: 'Bourg-en-Bresse')
            )
        );
    }

    private function getConnecteurInfo(int $id_ce): array
    {
        return $this->getObjectInstancier()->getInstance(ConnecteurEntiteSQL::class)->getInfo($id_ce);
    }
}
