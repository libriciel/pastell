<?php

declare(strict_types=1);

class TransformationGeneriqueTest extends PastellTestCase
{
    /**
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    private function getConnecteur(): TransformationGenerique
    {
        $id_ce = $this->createConnector(
            'transformation-generique',
            'Transformation generique'
        )['id_ce'];

        $connecteurConfig = $this->getDonneesFormulaireFactory()->getConnecteurEntiteFormulaire($id_ce);

        $connecteurConfig->addFileFromCopy(
            'definition',
            'definition.json',
            __DIR__ . '/fixtures/definition.json'
        );
        /** @var TransformationGenerique $connector */
        $connector = $this->getConnecteurFactory()->getConnecteurById($id_ce);
        return $connector;
    }

    /**
     * @throws DonneesFormulaireException
     * @throws JsonException
     * @throws UnrecoverableException
     */
    public function testExtraction(): void
    {
        $transformationGenerique = $this->getConnecteur();
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $transformationGenerique->transform($donneesFormulaire);
        static::assertSame('bar', $donneesFormulaire->get('foo'));
    }

    /**
     * @throws DonneesFormulaireException
     * @throws JsonException
     * @throws UnrecoverableException
     */
    public function testTestTransform(): void
    {
        $transformationGenerique = $this->getConnecteur();
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $info = $this->createDocument('test');
        $donneesFormulaire->id_d = $info['id_d'];
        static::assertSame(
            '{"foo":"bar","envoi_signature":"true","titre":"Ceci est mon titre","from_pa_metadata":"Bourg-en-Bresse Eric"}',
            $transformationGenerique->testTransform($donneesFormulaire)
        );
    }

    /**
     * @throws DonneesFormulaireException
     * @throws JsonException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testTransformWithData(): void
    {
        $connectorId = $this->createConnector(
            'transformation-generique',
            'Transformation generique'
        )['id_ce'];

        $connectorConfig = $this->getDonneesFormulaireFactory()->getConnecteurEntiteFormulaire($connectorId);

        $connectorData = TransformationGenerique::PA_CONNECTOR_DATA;
        $connectorConfig->addFileFromData(
            'definition',
            'definition.json',
            <<<TXT
{
  "foo": "{{jsonpath('$connectorData', '$.key') }}"
}
TXT,
        );
            $connectorConfig->addFileFromData(
                'data',
                'data.json',
                <<<TXT
{
    "key": "valueFromFile"
}
TXT,
            );
        /** @var TransformationGenerique $connector */
        $connector = $this->getConnecteurFactory()->getConnecteurById($connectorId);

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $connector->transform($donneesFormulaire);
        static::assertSame('valueFromFile', $donneesFormulaire->get('foo'));
    }
}
