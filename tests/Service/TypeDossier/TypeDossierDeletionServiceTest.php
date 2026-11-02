<?php

namespace Pastell\Tests\Service\TypeDossier;

use Pastell\Service\TypeDossier\TypeDossierDeletionService;
use PastellTestCase;
use TypeDossierException;
use TypeDossierSQL;

class TypeDossierDeletionServiceTest extends PastellTestCase
{
    /**
     * @throws TypeDossierException
     */
    public function testDelete()
    {
        $typeDossierSQL = $this->getObjectInstancier()->getInstance(TypeDossierSQL::class);
        $id_t = $this->copyTypeDossierTest();
        $this->assertTrue($typeDossierSQL->exists($id_t));
        $typeDossierDeletionService = $this->getObjectInstancier()->getInstance(
            TypeDossierDeletionService::class
        );
        $typeDossierDeletionService->delete($id_t);
        $this->assertFalse($typeDossierSQL->exists($id_t));

        $journal_message = $this->getJournalConsultationService()->getList()[0]['message'];

        $journal_export_json = '{' . explode('{', $journal_message, 2)[1];

        $journal_raw_data = json_encode(json_decode($journal_export_json, true)['raw_data']);

//        file_put_contents(__DIR__ . "/fixtures/type_dossier_delete_service_journal_message_raw_data.json", $journal_raw_data);
        $this->assertJsonStringEqualsJsonFile(
            __DIR__ . "/fixtures/type_dossier_delete_service_journal_message_raw_data.json",
            $journal_raw_data
        );

        $log_message = $this->getLogRecords()[0]['message'];
        $this->assertMatchesRegularExpression(
            "#Ajout au journal \(id_j=1\): 12 - 0 - 1 - 0 - Supprimé - Suppression du type de dossier id_t=1#",
            $log_message
        );
    }
}
