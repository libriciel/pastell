<?php

declare(strict_types=1);

class S2lowTest extends PastellTestCase
{
    use CurlUtilitiesTestTrait;

    private function getS2low($curl_response): S2low
    {
        $this->mockCurlWithCallable(function () use ($curl_response) {
            return $curl_response;
        });
        $form = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $form->setData('user_login', 'foo');

        $s2low = new S2low($this->getObjectInstancier());
        $s2low->setConnecteurConfig($form);

        return $s2low;
    }

    /**
     * @throws S2lowException
     */
    public function testPostHeliosS2lowOK(): void
    {
        $s2low = $this->getS2low('<import><resultat>OK</resultat><id>123</id></import>');
        static::assertEquals('123', $s2low->sendHelios(new Fichier()));
    }

    /**
     * @throws S2lowException
     */
    public function testPostHeliosS2lowKO(): void
    {
        $this->expectException(S2lowException::class);
        $this->expectExceptionMessage("Erreur lors de l'envoi du PES : foo");

        $s2low = $this->getS2low('<import><resultat>KO</resultat><message>foo</message></import>');
        $s2low->sendHelios(new Fichier());
    }

    /**
     * @throws S2lowException
     */
    public function testWhenGettingAccentuatedPesRetour(): void
    {
        $s2low = $this->getS2low(file_get_contents(__DIR__ . '/fixtures/HELIOS_SIMU_RETOUR_1565181244_184723364.xml'));

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $donneesFormulaire->setData('id_retour', '123');
        $donneesFormulaire->setData('objet', 'HELIOS_SIMU_RETOUR_1565181244_184723364');

        $s2low->getPESRetourLu($donneesFormulaire);
        static::assertStringEqualsFile(
            __DIR__ . '/fixtures/HELIOS_SIMU_RETOUR_1565181244_184723364.xml',
            $donneesFormulaire->getFileContent('fichier_pes')
        );
    }

    public function testPesFilenameSentToS2low(): void
    {
        $s2low = $this->getS2low('<import><resultat>OK</resultat></import>');
        static::assertSame('test-file_name.pdf', $s2low->getHeliosEnveloppeFileName('test-file_name.pdf'));
    }

    public function testPesAcquitIsNotAPesAcquit(): void
    {
        $this->expectException(RecoverableException::class);
        $this->expectExceptionMessage("Impossible d'analyser le fichier PES Acquit ");

        $s2low = $this->getS2low("I'm not a PES Acquit");
        $s2low->getFichierRetour('42');
    }

    /**
     * @throws S2lowException
     */
    public function testGetStatusHelios(): void
    {
        $s2low = $this->getS2low(file_get_contents(__DIR__ . '/fixtures/helios_status_ok.xml'));
        static::assertSame('8', $s2low->getStatusHelios('42'));
        static::assertStringEqualsFile(
            __DIR__ . '/fixtures/helios_status_ok.xml',
            $s2low->getLastReponseFile()
        );
    }

    public function testGetStatusHeliosWhenNotInXML(): void
    {
        $this->expectException(S2lowException::class);
        $this->expectExceptionMessage(
            "La réponse de S²low n'a pas pu être analysée (problème d'authentification ?)"
        );

        $s2low = $this->getS2low("I'm not in XML");
        $s2low->getStatusHelios('42');
    }

    public function testGetStatusHeliosWhenResponseIsKO(): void
    {
        $this->expectException(S2lowException::class);
        $this->expectExceptionMessage('Marche pas');

        $s2low = $this->getS2low(file_get_contents(__DIR__ . '/fixtures/helios_status_ko.xml'));
        $s2low->getStatusHelios('42');
    }
}
