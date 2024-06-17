<?php

namespace Pastell\Tests\Command\Studio;

use Exception;
use Pastell\Service\TypeDossier\TypeDossierExportFileToModuleService;
use PastellTestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use TmpFolder;
use TypeDossierException;

class PastellPackTest extends PastellTestCase
{
    private array $moduleDefinition = [
        'draft-ls-actes' => [
            '',
            'ls-actes',
            'Actes',
        ],
        'draft-ls-actes-publication' => [
            '',
            'ls-actes-publication',
            'Actes publication',
        ],
        'draft-ls-dossier-seance' => [
            '',
            'ls-dossier-seance',
            'Dossiers de séances (archivage)',
        ],
        'draft-ls-commande' => [
            '',
            'ls-commande',
            'Commande',
        ],
        'draft-ls-commande-destinataire' => [
            '',
            'ls-commande-destinataire',
            'Commande (destinataire)',
        ],
        'draft-ls-document-pdf' => [
            '',
            'ls-document-pdf',
            'Document PDF',
        ],
        'draft-ls-document-pdf-destinataire' => [
            '',
            'ls-document-pdf-destinataire',
            'Document PDF (destinataire)',
        ],
        'draft-ls-recup-parapheur' =>
            [
                'suppl_recup_fin_parapheur',
                'ls-recup-parapheur',
                'Récupération parapheur',
            ],
        'draft-ls-pes-pj' => [
            '',
            'ls-pes-pj',
            'Pièce jointe autonome PES',
        ],
        'draft-gfc-dossier' => [
            '',
            'gfc-dossier',
            'Dossier GFC'
        ],
        'draft-gfc-dossier-destinataire' => [
            '',
            'gfc-dossier-destinataire',
            'Dossier GFC (destinataire)'
        ],
        'draft-ls-helios' => [
            '',
            'ls-helios',
            'Helios',
        ],
        'draft-ls-mailsec' =>
            [
                '',
                'ls-mailsec',
                'Mail sécurisé',
            ],
        'draft-ls-mailsec-destinataire' =>
            [
                '',
                'ls-mailsec-destinataire',
                'Mail sécurisé (destinataire)',
            ],
        'draft-ls-mailsec-bidir' =>
            [
                '',
                'ls-mailsec-bidir',
                'Mail sécurisé avec réponse',
            ],
        'draft-ls-mailsec-bidir-destinataire' =>
            [
                '',
                'ls-mailsec-bidir-destinataire',
                'Mail sécurisé avec réponse (destinataire)',
            ],
        'draft-ls-mailsec-bidir-reponse' =>
            [
                '',
                'ls-mailsec-bidir-reponse',
                'Mail sécurisé avec réponse (réponse)',
            ],
        'draft-rh-archivage-collectif' => [
            'pack_rh',
            'rh-archivage-collectif',
            'Données de gestion collective (fichier unitaire) (archivage)'
        ],
        'draft-rh-archivage-collectif-zip' => [
            'pack_rh',
            'rh-archivage-collectif-zip',
            'Données de gestion collective (fichier compressé) (archivage)'
        ],
        'draft-rh-archivage-dossier-agent' => [
            'pack_rh',
            'rh-archivage-dossier-agent',
            "Eléments du dossier individuel de l'agent (archivage)"
        ],
        'draft-rh-bulletin-salaire-destinataire' => [
            'pack_rh',
            'rh-bulletin-salaire-destinataire',
            'Bulletin de salaire (destinataire)',
        ],
        'draft-document-autorisation-urbanisme' => [
            'pack_urbanisme',
            'document-autorisation-urbanisme',
            "Document d'autorisation d'urbanisme"
        ],
        'draft-rh-document-individuel' => [
            'pack_rh',
            'rh-document-individuel',
            'Document individuel'
        ],
        'draft-rh-document-individuel-destinataire' => [
            'pack_rh',
            'rh-document-individuel-destinataire',
            'Document individuel (destinataire)'
        ],
        'draft-rh-bulletin-salaire' => [
            'pack_rh',
            'rh-bulletin-salaire',
            'Bulletin de salaire'
        ],
        'draft-document-autorisation-urbanisme-destinataire' => [
            'pack_urbanisme',
            'document-autorisation-urbanisme-destinataire',
            "Document d'autorisation d'urbanisme (destinataire)"
        ],
        'draft-dossier-autorisation-urbanisme' => [
            'pack_urbanisme',
            'dossier-autorisation-urbanisme',
            "Dossiers d'autorisation d'urbanisme (archivage)"
        ],
    ];

    public function jsonProvider(): array
    {
        $result = [];
        $finder = new Finder();
        $paths = $finder->in(__DIR__ . '/../../../json-studio/')->files()->name('*.json');
        /** @var SplFileInfo $file */
        foreach ($paths as $file) {
            $data = array_merge(
                [$file->getPathname()],
                $this->moduleDefinition[$file->getFilenameWithoutExtension()]
                ??
                ['',$file->getFilenameWithoutExtension(),'']
            );
            $result[$file->getFilenameWithoutExtension()] = $data;
        }
        return $result;
    }

    /**
     * @dataProvider jsonProvider
     * @throws TypeDossierException
     * @throws Exception
     */
    public function testAllPack(
        string $jsonFilepath,
        string $restrictionPack,
        string $moduleId,
        string $moduleName
    ): void {
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();

        $typeDossierExportFileToModuleService =
            $this->getObjectInstancier()->getInstance(TypeDossierExportFileToModuleService::class);
        $typeDossierExportFileToModuleService->export(
            $jsonFilepath,
            $tmp_folder,
            $restrictionPack,
            $moduleId,
            $moduleName
        );

//        \file_put_contents(
//            __DIR__ . "/../../../module/$moduleId/definition.yml",
//            \file_get_contents($tmp_folder . "/$moduleId/definition.yml"),
//        );

        self::assertFileEquals(
            __DIR__ . "/../../../module/$moduleId/definition.yml",
            $tmp_folder . "/$moduleId/definition.yml"
        );
        $tmpFolder->delete($tmp_folder);
    }
}
