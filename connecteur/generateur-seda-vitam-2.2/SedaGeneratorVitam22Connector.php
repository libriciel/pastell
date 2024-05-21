<?php

declare(strict_types=1);

use Pastell\Connector\AbstractSedaGeneratorConnector;
use Pastell\Seda\Message\VitamSedaMessageBuilder;
use Pastell\Seda\SedaVersion;

final class SedaGeneratorVitam22Connector extends AbstractSedaGeneratorConnector
{
    public function __construct(
        private readonly CurlWrapperFactory $curlWrapperFactory,
        private readonly ConnecteurFactory $connecteurFactory,
        private readonly TmpFolder $tmpFolder,
        private readonly VitamSedaMessageBuilder $sedaMessageBuilder,
    ) {
        parent::__construct(
            $this->curlWrapperFactory,
            $this->connecteurFactory,
            $this->tmpFolder,
            $this->sedaMessageBuilder
        );
    }

    public function getVersion(): SedaVersion
    {
        return SedaVersion::VERSION_2_2_VITAM;
    }

    /**
     * @throws UnrecoverableException
     */
    public function getAlgorithmIdentifier(string $algorithm): string
    {
        return match ($algorithm) {
            'sha256' => 'SHA-256',
            'sha512' => 'SHA-512',
            default => throw new UnrecoverableException('Algorithme non supporté'),
        };
    }

    public function getPastellToSeda(): array
    {
        $pastellToSeda = parent::getPastellToSeda();

        $pastellToSeda['AppraisalRule_Rule']['commentaire'] = 'AppraisalRule - Rule (forme attendue encoder en xsd:duration, voir http://www.datypic.com/sc/xsd/t-xsd_duration.html)';
        $pastellToSeda['AppraisalRule_FinalAction']['commentaire'] = 'AppraisalRule - FinalAction (forme attendue: Conserver OU Détruire)';
        $pastellToSeda['AccessRule_Rule']['commentaire'] = 'AccessRule - Rule (forme attendue : de AR038 à AR062)';
        $pastellToSeda['AccessRule_StartDate']['commentaire'] = 'AccessRule - StartDate (forme attentue Y-m-d)';
        $pastellToSeda['titre']['commentaire'] = 'ArchiveUnit - Title';
        $pastellToSeda['archiveunits_title']['commentaire'] = 'ArchiveUnit - Description';
        $pastellToSeda['CustodialHistory']['commentaire'] = 'ArchiveUnit - CustodialHistoryItem';
        $pastellToSeda['Language']['commentaire'] = 'Language (forme attendue: fr)';
        $pastellToSeda['DescriptionLanguage']['commentaire'] = 'DescriptionLanguage (forme attendue: fr)';
        $pastellToSeda['StartDate']['commentaire'] = 'StartDate (forme attendue Y-m-d)';
        $pastellToSeda['EndDate']['commentaire'] = 'EndDate (forme attendue Y-m-d)';
        $pastellToSeda['archival_agreement']['libelle'] = "Identifiant du contrat d'entrée";

        return \array_merge(
            $pastellToSeda,
            [
                'OriginatingAgencyIdentifier' => [
                    'seda' => 'OriginatingAgencyIdentifier',
                    'libelle' => 'Identifiant du service producteur',
                    'commentaire' => 'OriginatingAgencyIdentifier',
                ],
                'SubmissionAgencyIdentifier' => [
                    'seda' => 'SubmissionAgencyIdentifier',
                    'libelle' => 'Identifiant du service versant',
                    'commentaire' => 'SubmissionAgencyIdentifier',
                ],
                'TransferringAgencyArchiveIdentifier' => [
                    'seda' => 'TransferringAgencyArchiveIdentifier',
                    'libelle' => "Identifiant donné à l'archive par le service versant",
                    'commentaire' => 'TransferringAgencyArchiveUnitIdentifier',
                ],
                'ArchiveUnit_ExternalReferenceName' => [
                    'seda' => 'ExternalReferenceName',
                    'libelle' => 'Nom de la métadonnée de rattachement',
                    'commentaire' => <<<EOT
UpdateOperation.ArchiveUnitIdentifierKey.MetadataName <br />
Doit correspondre à une balise du bloc "Content" du schéma SEDA 2.1
EOT,
                ],
                'ArchiveUnit_ExternalReference' => [
                    'seda' => 'ExternalReference',
                    'libelle' => 'Référence à une unité d\'archive',
                    'commentaire' => 'UpdateOperation.ArchiveUnitIdentifierKey.MetadataValue',
                ],
            ]
        );
    }

    /**
     * @throws UnrecoverableException
     * @throws SimpleXMLWrapperException
     * @throws \JsonException
     * @throws DonneesFormulaireException
     */
    public function generateArchiveThrow(FluxData $fluxData, string $archive_path, string $tmp_folder): void
    {
        \file_put_contents("$tmp_folder/manifest.xml", $this->getBordereau($fluxData));
        parent::generateArchiveThrow($fluxData, $archive_path, $tmp_folder);
    }
}
