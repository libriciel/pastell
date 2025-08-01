<?php

declare(strict_types=1);

namespace Pastell\Tests\Step\Tdt\Acte\lib;

use Exception;
use Pastell\Step\Tdt\Acte\lib\ActesTypePJ;
use Pastell\Step\Tdt\Acte\lib\ActesTypePJData;
use PHPUnit;

class ActesTypePJTest extends PHPUnit\Framework\TestCase
{
    /**
     * @throws Exception
     */
    public function testGetTypePJListe(): void
    {

        $actesTypePJData = new ActesTypePJData();

        $actesTypePJData->classificationFilePath = __DIR__ . '/../fixtures/classification.xml';
        $actesTypePJData->acteNature = '4';

        $actesTypePJ = new ActesTypePJ();
        $result = $actesTypePJ->getTypePJListe($actesTypePJData);
        $expected_value = [
            '99_CO' => 'Contrat (99_CO)',
            '42_AT' => 'Attestation (42_AT)',
            '42_AC' => 'Avenant au contrat (42_AC)',
            '12_AD' => 'Avis de délégation (12_AD)',
            '11_AV' => 'Avis du jury de concours (11_AV)',
            '12_CC' => 'Cahier des charges (12_CC)',
            '11_AP' => 'Cahier des clauses administratives particulières (11_AP)',
            '11_TP' => 'Cahier des clauses techniques particulières (11_TP)',
            '33_CC' => 'Contrat de concession (33_CC)',
            '12_CD' => 'Contrat de délégation (12_CD)',
            '32_CV' => 'Contrat de vente (32_CV)',
            '12_CR' => 'Courriers de rejet des offres incomplètes ou irrecevables (12_CR)',
            '12_DC' => 'Documents de consultation (12_DC)',
            '31_DP' => 'Documents pré-contractuels (31_DP)',
            '32_DP' => 'Documents pré-contractuels (32_DP)',
            '42_DE' => 'Délibération (42_DE)',
            '10_DE' => 'Délibération autorisant à passer le contrat (10_DE)',
            '32_DC' => 'Délibération constatant la désaffectation (32_DC)',
            '32_DE' => 'Délibération de déclassement (32_DE)',
            '11_IN' => 'Invitation des candidats à soumissionner (11_IN)',
            '12_IP' => 'Invitation à présenter une offre (12_IP)',
            '12_NR' => 'Notification du rejet des offres (12_NR)',
            '12_RS' => 'Rapport de sélection du délégataire (12_RS)',
            '11_JU' => 'Rapport justifiant le choix du marché, les modalités et la procédure de passation (11_JU)',
            '17_RC' => 'Règlement de concours (17_RC)',
            '11_RC' => 'Règlement de consultation (11_RC)',
            '12_ST' => 'Spécifications techniques et fonctionnelles (12_ST)',
        ];
        self::assertSame($expected_value, $result);
    }
}
