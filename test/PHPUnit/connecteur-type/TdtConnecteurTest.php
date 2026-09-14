<?php

class TdtConnecteurTest extends PastellTestCase
{
    /**
     * @var TdtConnecteur $tdtConnecteur
     */
    private $tdtConnecteur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tdtConnecteur = $this->getMockForAbstractClass(TdtConnecteur::class);
    }

    public static function getShortenedNatureActeProvider()
    {
        return [
            'DE' => [1, 'DE'],
            'AR' => [2, 'AR'],
            'AI' => [3, 'AI'],
            'CC' => [4, 'CC'],
            'BF' => [5, 'BF'],
            'AU' => [6, 'AU'],
        ];
    }

    /**
     * @dataProvider getShortenedNatureActeProvider
     * @throws Exception
     */
    public function testGetShortenedNatureActe($natureActe, $expectedValue)
    {
        $this->assertSame(
            $expectedValue,
            $this->tdtConnecteur->getShortenedNatureActe($natureActe)
        );
    }

    /**
     * @throws Exception
     */
    public function testGetShortenedNatureActeException()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("La nature 8 est inconnue.");
        $this->tdtConnecteur->getShortenedNatureActe('8');
    }

    public static function getIntNatureActeProvider()
    {
        return [
            1 => ['DE', 1],
            2 => ['AR', 2],
            3 => ['AI', 3],
            4 => ['CC', 4],
            5 => ['BF', 5],
            6 => ['AU', 6],
        ];
    }

    /**
     * @dataProvider getIntNatureActeProvider
     * @throws Exception
     */
    public function testGetIntNatureActe($shortenedNatureActe, $expectedValue)
    {
        $this->assertSame(
            $expectedValue,
            $this->tdtConnecteur->getIntNatureActe($shortenedNatureActe)
        );
    }

    /**
     * @throws Exception
     */
    public function testGetIntNatureActeException()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("La nature XX est inconnue.");
        $this->tdtConnecteur->getIntNatureActe('XX');
    }

    public static function getStatusInfoProvider()
    {
        return [
            [-1, "Erreur"],
            [0, "Annulé"],
            [1, "Posté"],
            [2, "En attente de transmission. Fichier valide."],
            [3, "Transmis"],
            [4, "Acquittement reçu"],
            [5, "status 5 invalide"],
            [6, "Refusé"],
            [7, "En traitement"],
            [8, "Information disponible"],
            [9, "Status 9 inconnu sur Pastell"],
        ];
    }

    /**
     * @dataProvider getStatusInfoProvider
     * @throws Exception
     */
    public function testGetStatusInfo($status, $expectedValue)
    {
        $this->assertSame(
            $expectedValue,
            $this->tdtConnecteur->getStatusInfo($status)
        );
    }

    public static function getStatusStringProvider()
    {
        return [
            [-1, 'Erreur'],
            [0, 'Annulé'],
            [1, 'Posté'],
            [2, 'En attente de transmission'],
            [3, 'Transmis'],
            [4, 'Acquittement reçu'],
            [5, 'Validé'],
            [6, 'Refusé'],
            [7, 'AR non disponible pour le moment'],
            [17, "En attente d'être postée"],
            [18, "Statut inconnu (18)"],
        ];
    }

    /**
     * @dataProvider getStatusStringProvider
     * @throws Exception
     */
    public function testGetStatusString($status, $expectedValue)
    {
        $this->assertSame(
            $expectedValue,
            $this->tdtConnecteur::getStatusString($status)
        );
    }

    public static function getTransactionNameFromNumberProvider()
    {
        return [
            [TdtConnecteur::COURRIER_SIMPLE, 'Courrier simple'],
            [TdtConnecteur::DEMANDE_PIECE_COMPLEMENTAIRE, 'Demande de pièces complémentaires'],
            [TdtConnecteur::LETTRE_OBSERVATION, 'Lettre d\'observation'],
            [TdtConnecteur::DEFERE_TRIBUNAL_ADMINISTRATIF, 'Déféré au tribunal administratif'],
            [18, "Transaction inconnue (18)"],
        ];
    }

    /**
     * @dataProvider getTransactionNameFromNumberProvider
     * @throws Exception
     */
    public function testGetTransactionNameFromNumber($type_reponse, $expectedValue)
    {
        $this->assertSame(
            $expectedValue,
            $this->tdtConnecteur::getTransactionNameFromNumber($type_reponse)
        );
    }
}
