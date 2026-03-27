<?php

namespace Pastell\Service\Document;

use DocumentSQL;
use DonneesFormulaireFactory;

class DocumentTitre
{
    private $documentSQL;
    private $donneesFormulaireFactory;

    public function __construct(DocumentSQL $documentSQL, DonneesFormulaireFactory $donneesFormulaireFactory)
    {
        $this->documentSQL = $documentSQL;
        $this->donneesFormulaireFactory = $donneesFormulaireFactory;
    }

    public function update(string $id_d): void
    {
        $donnesFormulaire = $this->donneesFormulaireFactory->get($id_d);
        $this->documentSQL->setTitre($id_d, $donnesFormulaire->getTitre());
    }
}
