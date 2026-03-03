<?php

class S2lowTest extends PastellTestCase
{
    private function getS2low($curl_response): S2low
    {
        $curlWrapper = $this->createMock(CurlWrapper::class);

        $curlWrapper
            ->method('get')
            ->willReturn($curl_response);

        $curlWrapperFactory = $this->createMock(CurlWrapperFactory::class);

        $curlWrapperFactory
            ->method('getInstance')
            ->willReturn($curlWrapper);

        $objectInstancier = $this->createMock(ObjectInstancier::class);

        $objectInstancier->method('getInstance')->willReturn($curlWrapperFactory);

        $collectiviteProperties = $this->createMock(DonneesFormulaire::class);

        $collectiviteProperties
            ->method('get')
            ->willReturnCallback(function ($a) {

                $result = ['user_login' => 'foo'];
                if (isset($result[$a])) {
                    return $result[$a];
                }
                return false;
            });

        $s2low = new S2low($objectInstancier);
        $s2low->setConnecteurConfig($collectiviteProperties);

        return $s2low;
    }

    protected function getDonneesFormulaire(): DonneesFormulaire
    {
        return $this->createMock(DonneesFormulaire::class);
    }

    /**
     * @throws S2lowException
     */
    public function testPostHeliosS2lowOK(): void
    {
        $s2low = $this->getS2low("<import><resultat>OK</resultat><id>123</id></import>");
        $this->assertEquals('123', $s2low->sendHelios(new Fichier()));
    }

    /**
     * @throws S2lowException
     */
    public function testPostHeliosS2lowKO(): void
    {
        $s2low = $this->getS2low("<import><resultat>KO</resultat><message>foo</message></import>");
        $this->expectExceptionMessage("Erreur lors de l'envoi du PES : foo");
        $s2low->sendHelios(new Fichier());
    }

    /**
     * @throws S2lowException
     */
    public function testWhenGettingAccentuatedPesRetour()
    {
        $s2low = $this->getS2low(file_get_contents(__DIR__ . '/fixtures/HELIOS_SIMU_RETOUR_1565181244_184723364.xml'));

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $donneesFormulaire->setData('id_retour', '123');
        $donneesFormulaire->setData('objet', 'HELIOS_SIMU_RETOUR_1565181244_184723364');

        $s2low->getPESRetourLu($donneesFormulaire);
        $this->assertSame(
            file_get_contents(__DIR__ . '/fixtures/HELIOS_SIMU_RETOUR_1565181244_184723364.xml'),
            $donneesFormulaire->getFileContent('fichier_pes')
        );
    }

    public function testPesFilenameSentToS2low()
    {
        $s2low = $this->getS2low("<import><resultat>OK</resultat></import>");
        $this->assertSame("test-file_name.pdf", $s2low->getHeliosEnveloppeFileName('test-file_name.pdf'));
    }

    public function testPesAcquitIsNotAPesAcquit()
    {
        $s2low = $this->getS2low("I'm not a PES Acquit");
        $this->expectException(RecoverableException::class);
        $this->expectExceptionMessage("Impossible d'analyser le fichier PES Acquit ");
        $s2low->getFichierRetour("42");
    }

    /**
     * @throws S2lowException
     */
    public function testGetStatusHelios()
    {
        $s2low = $this->getS2low(file_get_contents(__DIR__ . "/fixtures/helios_status_ok.xml"));
        $this->assertEquals(8, $s2low->getStatusHelios("42"));
        $this->assertStringEqualsFile(
            __DIR__ . "/fixtures/helios_status_ok.xml",
            $s2low->getLastReponseFile()
        );
    }

    public function testGetStatusHeliosWhenNotInXML()
    {
        $s2low = $this->getS2low("I'm not in XML");
        $this->expectException(S2lowException::class);
        $this->expectExceptionMessage(
            "La réponse de S²low n'a pas pu être analysée (problème d'authentification ?)"
        );
        $s2low->getStatusHelios("42");
    }

    public function testGetStatusHeliosWhenResponseIsKO()
    {
        $s2low = $this->getS2low(file_get_contents(__DIR__ . "/fixtures/helios_status_ko.xml"));
        $this->expectException(S2lowException::class);
        $this->expectExceptionMessage(
            "Marche pas"
        );
        $s2low->getStatusHelios("42");
    }
}
