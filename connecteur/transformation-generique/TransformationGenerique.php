<?php

use Pastell\Service\Document\DocumentTransformService;

class TransformationGenerique extends TransformationConnecteur
{
    public const PA_CONNECTOR_DATA = 'pa_connector_data';
    private DonneesFormulaire $connecteurConfig;

    public function __construct(
        private readonly TransformationGeneriqueDefinition $transformationGeneriqueDefinition,
        private readonly DocumentTransformService $documentTransformService
    ) {
    }

    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire): void
    {
        $this->connecteurConfig = $donneesFormulaire;
    }

    /**
     * @throws DonneesFormulaireException
     * @throws JsonException
     * @throws UnrecoverableException
     */
    public function transform(DonneesFormulaire $donneesFormulaire): array
    {
        if ($this->connecteurConfig->get('data')) {
            $donneesFormulaire->addFileFromCopy(
                self::PA_CONNECTOR_DATA,
                '',
                $this->connecteurConfig->getFilePath('data')
            );
        }

        return $this->documentTransformService->transform(
            $donneesFormulaire,
            $this->transformationGeneriqueDefinition->getData($this->connecteurConfig)
        );
    }

    /**
     * @throws DonneesFormulaireException
     * @throws JsonException
     * @throws UnrecoverableException
     */
    public function testTransform(DonneesFormulaire $donneesFormulaire): string
    {
        if ($this->connecteurConfig->get('data')) {
            $donneesFormulaire->addFileFromCopy(
                self::PA_CONNECTOR_DATA,
                '',
                $this->connecteurConfig->getFilePath('data')
            );
        }

        $result = $this->documentTransformService->getNewValue(
            $donneesFormulaire,
            $this->transformationGeneriqueDefinition->getData($this->connecteurConfig)
        );
        return json_encode($result, JSON_THROW_ON_ERROR);
    }
}
